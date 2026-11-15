<?php

namespace App\Services\Eve;

use App\Models\EveCharacter;
use Illuminate\Support\Facades\Http;
use Throwable;

class CharacterContracts
{
    private const CONTRACT_SCOPE = 'esi-contracts.read_character_contracts.v1';

    /**
     * Retrieve the latest EVE contracts for the linked My Eve Online character.
     *
     * @return array{status:string,message:?string,contracts:array<int,array<string,mixed>>}
     */
    public function latest(EveCharacter $character, int $limit = 10): array
    {
        $scopes = $character->scopes ?? [];
        if (is_string($scopes)) {
            $scopes = preg_split('/\\s+/', $scopes, -1, PREG_SPLIT_NO_EMPTY);
        }
        if (!is_array($scopes) || !in_array(self::CONTRACT_SCOPE, $scopes, true)) {
            return [
                'status' => 'reauthorize',
                'message' => 'My Eve Online needs to be linked with the EVE contract-read permission. Use “Link another character” to authorize it again.',
                'contracts' => [],
            ];
        }

        try {
            $token = $this->validAccessToken($character);
            $characterId = (int) $character->eve_character_id;
            $response = Http::withToken($token)
                ->withHeaders([
                    'User-Agent' => config('buyback.eve_user_agent'),
                    'X-Compatibility-Date' => config('buyback.esi_compatibility_date'),
                ])
                ->acceptJson()
                ->timeout(30)
                ->retry(2, 500)
                ->get("https://esi.evetech.net/latest/characters/{$characterId}/contracts/", ['page' => 1])
                ->throw();

            $contracts = collect($response->json() ?? [])
                ->sortByDesc(fn (array $contract) => (string) ($contract['date_issued'] ?? ''))
                ->take(max(1, $limit))
                ->values()
                ->all();

            $partyIds = [];
            foreach ($contracts as $contract) {
                foreach (['issuer_id', 'acceptor_id', 'assignee_id', 'issuer_corporation_id'] as $field) {
                    $id = (int) ($contract[$field] ?? 0);
                    if ($id > 0 && $id !== $characterId) {
                        $partyIds[$id] = $id;
                    }
                }
            }
            $names = $this->resolveNames(array_values($partyIds));

            $rows = array_map(function (array $contract) use ($characterId, $names): array {
                $otherPartyId = $this->otherPartyId($contract, $characterId);
                return [
                    'contract_id' => (int) ($contract['contract_id'] ?? 0),
                    'type' => (string) ($contract['type'] ?? 'unknown'),
                    'status' => (string) ($contract['status'] ?? 'unknown'),
                    'availability' => (string) ($contract['availability'] ?? 'unknown'),
                    'date_issued' => $contract['date_issued'] ?? null,
                    'date_expired' => $contract['date_expired'] ?? null,
                    'date_completed' => $contract['date_completed'] ?? null,
                    'price' => (float) ($contract['price'] ?? 0),
                    'reward' => (float) ($contract['reward'] ?? 0),
                    'collateral' => (float) ($contract['collateral'] ?? 0),
                    'other_party_id' => $otherPartyId,
                    'other_party_name' => $otherPartyId ? ($names[$otherPartyId] ?? ('EVE ID ' . $otherPartyId)) : '—',
                ];
            }, $contracts);

            return ['status' => 'ready', 'message' => null, 'contracts' => $rows];
        } catch (Throwable $exception) {
            report($exception);
            return [
                'status' => 'error',
                'message' => 'EVE could not return contracts for My Eve Online just now. Please try again shortly.',
                'contracts' => [],
            ];
        }
    }

    private function validAccessToken(EveCharacter $character): string
    {
        if ($character->access_token && $character->token_expires_at && $character->token_expires_at->isFuture()) {
            return $character->access_token;
        }

        abort_unless($character->refresh_token, 403, 'My Eve Online needs to be re-authorized with EVE SSO.');

        $response = Http::asForm()
            ->withBasicAuth(
                (string) config('services.eve.client_id'),
                (string) config('services.eve.client_secret')
            )
            ->post('https://login.eveonline.com/v2/oauth/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $character->refresh_token,
            ])
            ->throw()
            ->json();

        $character->forceFill([
            'access_token' => $response['access_token'],
            'refresh_token' => $response['refresh_token'] ?? $character->refresh_token,
            'token_expires_at' => now()->addSeconds((int) ($response['expires_in'] ?? 1200)),
        ])->save();

        return (string) $response['access_token'];
    }

    private function otherPartyId(array $contract, int $characterId): ?int
    {
        $candidateFields = (int) ($contract['issuer_id'] ?? 0) === $characterId
            ? ['assignee_id', 'acceptor_id', 'issuer_corporation_id']
            : ['issuer_id', 'issuer_corporation_id', 'assignee_id', 'acceptor_id'];

        foreach ($candidateFields as $field) {
            $id = (int) ($contract[$field] ?? 0);
            if ($id > 0 && $id !== $characterId) {
                return $id;
            }
        }

        return null;
    }

    /**
     * @param array<int,int> $ids
     * @return array<int,string>
     */
    private function resolveNames(array $ids): array
    {
        if (!$ids) {
            return [];
        }

        try {
            $rows = Http::withHeaders([
                    'User-Agent' => config('buyback.eve_user_agent'),
                    'X-Compatibility-Date' => config('buyback.esi_compatibility_date'),
                ])
                ->acceptJson()
                ->timeout(15)
                ->post('https://esi.evetech.net/latest/universe/names/', array_values($ids))
                ->throw()
                ->json();

            $names = [];
            foreach ($rows ?? [] as $row) {
                if (isset($row['id'], $row['name'])) {
                    $names[(int) $row['id']] = (string) $row['name'];
                }
            }
            return $names;
        } catch (Throwable $exception) {
            report($exception);
            return [];
        }
    }
}
