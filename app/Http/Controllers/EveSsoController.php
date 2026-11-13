<?php

namespace App\Http\Controllers;

use App\Models\EveCharacter;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class EveSsoController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return $this->beginAuthentication(false);
    }

    public function linkCharacter(): RedirectResponse
    {
        abort_unless(Auth::check(), 401);
        return $this->beginAuthentication(true);
    }

    private function beginAuthentication(bool $linkToCurrentAccount): RedirectResponse
    {
        abort_unless(
            config('services.eve.client_id') && config('services.eve.client_secret') && config('services.eve.callback_url'),
            503,
            'EVE SSO is not configured.'
        );

        $state = Str::random(48);
        // Keep OAuth state outside the browser session: the pre-auth session cookie
        // can be discarded during the external EVE SSO redirect.
        Cache::put('eve_sso:state:' . hash('sha256', $state), [
            'link_character' => $linkToCurrentAccount,
            'user_id' => $linkToCurrentAccount ? Auth::id() : null,
        ], now()->addMinutes(10));

        $query = http_build_query([
            'response_type' => 'code',
            'redirect_uri' => config('services.eve.callback_url'),
            'client_id' => config('services.eve.client_id'),
            'scope' => implode(' ', config('services.eve.scopes', ['publicData'])),
            'state' => $state,
        ]);

        return redirect('https://login.eveonline.com/v2/oauth/authorize/?' . $query);
    }

    public function callback(Request $request): RedirectResponse
    {
        $provided = (string) $request->query('state');
        abort_unless($request->filled('code') && $provided !== '', 419, 'The EVE login response was incomplete. Please start sign-in again.');

        // Pull makes the state single-use without depending on the pre-auth session cookie.
        $stateData = Cache::pull('eve_sso:state:' . hash('sha256', $provided));
        abort_unless(is_array($stateData), 419, 'Your EVE sign-in expired or its security state could not be verified. Please start sign-in again.');

        $linkToCurrentAccount = (bool) ($stateData['link_character'] ?? false);
        $stateUserId = isset($stateData['user_id']) ? (int) $stateData['user_id'] : null;
        abort_unless(!$linkToCurrentAccount || $stateUserId, 401, 'Your session expired while linking the EVE character. Sign in again and retry.');

        $tokens = Http::asForm()
            ->withBasicAuth((string) config('services.eve.client_id'), (string) config('services.eve.client_secret'))
            ->post('https://login.eveonline.com/v2/oauth/token', [
                'grant_type' => 'authorization_code',
                'code' => $request->query('code'),
            ])->throw()->json();

        $verify = Http::withToken($tokens['access_token'])
            ->get('https://login.eveonline.com/oauth/verify')->throw()->json();

        $characterId = (int) ($verify['CharacterID'] ?? 0);
        abort_unless($characterId > 0, 403, 'EVE did not return a valid character identity.');

        $identity = Http::withHeaders([
            'User-Agent' => config('buyback.eve_user_agent'),
            'X-Compatibility-Date' => config('buyback.esi_compatibility_date'),
        ])->timeout(20)->get("https://esi.evetech.net/latest/characters/{$characterId}/")->throw()->json();

        $ownerHash = (string) ($verify['CharacterOwnerHash'] ?? '');
        abort_unless($ownerHash !== '', 403, 'EVE did not return character ownership proof.');

        $currentUserId = $stateUserId;

        $user = DB::transaction(function () use ($characterId, $verify, $tokens, $identity, $ownerHash, $linkToCurrentAccount, $currentUserId) {
            $character = EveCharacter::where('eve_character_id', $characterId)->lockForUpdate()->first();

            if ($character && $character->owner_hash && !hash_equals($character->owner_hash, $ownerHash)) {
                abort(403, 'EVE ownership of this character has changed. Contact a Buyback administrator to review the existing account link.');
            }

            if ($linkToCurrentAccount) {
                $user = User::findOrFail($currentUserId);
                if ($character && $character->user_id && $character->user_id !== $user->id) {
                    abort(409, 'This EVE character is already linked to another Buyback account. Sign out and use Sign in with EVE to access that account.');
                }
            } else {
                // A normal EVE login always enters the account that owns this character.
                // Never attach it to whichever unrelated account happens to be open in this browser.
                $user = $character?->user;
                if (!$user) {
                    $adminIds = array_map('intval', config('services.eve.admin_character_ids', []));
                    $user = User::create([
                        'name' => $identity['name'] ?? 'EVE Pilot',
                        'role' => in_array($characterId, $adminIds, true) ? 'admin' : 'member',
                        'active' => true,
                    ]);
                }
            }

            $adminIds = array_map('intval', config('services.eve.admin_character_ids', []));
            if (in_array($characterId, $adminIds, true) && $user->role !== 'admin') {
                $user->forceFill(['role' => 'admin'])->save();
            }

            $character ??= new EveCharacter(['eve_character_id' => $characterId]);
            $character->fill([
                'user_id' => $user->id,
                'name' => $identity['name'] ?? 'EVE Pilot',
                'owner_hash' => $ownerHash,
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? null,
                'token_expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 1200)),
                'scopes' => $verify['Scopes'] ?? [],
                'last_authenticated_at' => now(),
            ])->save();

            $user->forceFill([
                'name' => $identity['name'] ?? $user->name,
                'last_login_at' => now(),
            ])->save();

            return $user;
        });

        abort_unless($user->active, 403, 'This account has been disabled.');

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
