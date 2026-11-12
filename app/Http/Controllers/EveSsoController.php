<?php

namespace App\\Http\\Controllers;

use App\\Models\\EveCharacter;
use App\\Models\\User;
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\Auth;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Http;
use Illuminate\\Support\\Str;

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
        session([
            'eve_sso_state' => $state,
            'eve_sso_link_character' => $linkToCurrentAccount,
            'url.intended' => $linkToCurrentAccount ? route('dashboard') : null,
        ]);

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
        $expected = (string) session('eve_sso_state');
        $provided = (string) $request->query('state');
        abort_unless($request->filled('code') && $expected !== '' && hash_equals($expected, $provided), 419, 'Invalid EVE SSO state.');

        $linkToCurrentAccount = (bool) session('eve_sso_link_character', false);
        $request->session()->forget(['eve_sso_state', 'eve_sso_link_character']);

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

        $currentUserId = Auth::id();
        abort_unless(!$linkToCurrentAccount || $currentUserId, 401);

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

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
