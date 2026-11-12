<?php

namespace App\Services\Eve;

use App\Models\EveCharacter;
use Illuminate\Support\Facades\Http;

class CharacterContractVerifier
{
    public function verifyCourierDelivery(EveCharacter $character, int $contractId): array
    {
        $required = 'esi-contracts.read_character_contracts.v1';
        $scopes = $character->scopes ?? [];

        if (is_string($scopes)) {
            $scopes = preg_split('/\s+/', $scopes, -1, PREG_SPLIT_NO_EMPTY);
        }

        if (!is_array($scopes)) {
            $scopes = [];
        }

        abort_unless(
            in_array($required, $scopes, true),
            403,
            'Runner must re-authorize EVE SSO with the character-contract read scope.'
        );

        $token = $character->access_token;
        if (!$token || !$character->token_expires_at || $character->token_expires_at->isPast()) {
            $token = $this->refresh($character);
        }

        $characterId = (int) $character->eve_character_id;
        $http = Http::withToken($token)
            ->withHeaders(['User-Agent' => config('buyback.eve_user_agent'), 'X-Compatibility-Date' => config('buyback.esi_compatibility_date')])
            ->acceptJson()
            ->timeout(30)
            ->retry(3, 500);

        $pages = 1;
        $contract = null;

        for ($page = 1; $page <= $pages; $page++) {
            $response = $http->get(
                "https://esi.evetech.net/latest/characters/{$characterId}/contracts/",
                ['page' => $page]
            )->throw();

            $pages = min(1000, max(1, (int) $response->header('X-Pages', 1)));

            foreach ($response->json() as $candidate) {
                if ((int) ($candidate['contract_id'] ?? 0) === $contractId) {
                    $contract = $candidate;
                    break 2;
                }
            }

            usleep(100000);
        }

        abort_unless(
            $contract !== null,
            422,
            'The courier contract is not visible in this runner character\'s ESI contract list.'
        );

        abort_unless(
            ($contract['type'] ?? null) === 'courier',
            422,
            'Only courier contracts can complete runner jobs.'
        );

        abort_unless(
            in_array(($contract['status'] ?? null), ['finished_contractor', 'finished'], true),
            422,
            'EVE does not report this courier contract as completed by the runner.'
        );

        abort_unless(
            (int) ($contract['acceptor_id'] ?? 0) === $characterId,
            403,
            'The authenticated runner character did not accept this contract.'
        );

        return [
            'verified' => true,
            'contract_id' => $contractId,
            'character_id' => $characterId,
            'type' => $contract['type'],
            'status' => $contract['status'],
            'acceptor_id' => $contract['acceptor_id'],
            'date_completed' => $contract['date_completed'] ?? null,
            'verified_at' => now()->toIso8601String(),
        ];
    }

    private function refresh(EveCharacter $character): string
    {
        abort_unless($character->refresh_token, 403, 'EVE session expired. Sign in with EVE again.');

        $response = Http::asForm()
            ->withBasicAuth(
                (string) config('services.eve.client_id'),
                (string) config('services.eve.client_secret')
            )
            ->post('https://login.eveonline.com/v2/oauth/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $character->refresh_token,
            ]);

        if (!$response->successful()) {
            abort(403, 'Unable to refresh EVE authorization. Sign in with EVE again.');
        }

        $data = $response->json();

        $character->forceFill([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $character->refresh_token,
            'token_expires_at' => now()->addSeconds((int) ($data['expires_in'] ?? 1200)),
        ])->save();

        return $data['access_token'];
    }
}
