<?php

namespace App\Services\Eve;

use App\Models\EveCharacter;
use Illuminate\Support\Facades\Http;
use Throwable;

class CharacterContracts
{
    private const CONTRACT_SCOPE = 'esi-contracts.read_character_contracts.v1';

    /**
     * Retrieve contracts issued by this character to the configured buyback recipient.
     *
     * EVE's assignee_id is the contract's "To" party. Only outstanding and
     * completed contracts addressed to the named recipient are returned.
     *
     * @return array{status:string,message:?string,contracts:array<int,array<string,mixed>>}
     */
    public function forRecipient(EveCharacter $character, string $recipientName = 'My Eve Online', int $limit = 100): array
    {
        $scopes = $character->scopes ?? [];
        if (is_string($scopes)) {
            $scopes = preg_split('/\\s+/', $scopes, -1, PREG_SPLIT_NO_EMPTY);
        }
        if (!is_array($scopes) || !in_array(self::CONTRACT_SCOPE, $scopes, true)) {
            return [
                'status' => 'reauthorize',
                'message' => 'Authorize your linked EVE character to read its contracts so Buyback can find contracts addressed to My Eve Online.',
                'contracts' => [],
            ];
        }

        try {
            $token = $this->validAccessToken($character);
            $characterId = (int) $character->eve_character_id;
            $http = Http::withToken($token)
                ->withHeaders([
                    'User-Agent' => config('buyback.eve_user_agent'),
                    'X-Compatibility-Date' => config('buyback.esi_compatibility_date'),
                ])
                ->acceptJson()
                ->timeout(30)
                ->retry(2, 500);

            $all = [];
            $pages = 1;
            for ($page = 1; $page <= $pages; $page++) {
                $response = $http->get(
                    "https://esi.evetech.net/latest/characters/{$characterId}/contracts/",
                    ['page' => $page]
                )->throw();
                $pages = min(1000, max(1, (int) $response->header('X-Pages', 1)));
                foreach ($response->json() ?? [] as $contract) {
                    $status = (string) ($contract['status'] ?? '');
                    if (!in_array($status, ['outstanding', 'in_progress', 'finished', 'finished_issuer', 'finished_contractor'], true)) {
                        continue;
                    }
                    $assigneeId = (int) ($contract['assignee_id'] ?? 0);
                    if ($assigneeId > 0) {
                        $all[] = $contract;
                    }
                }
            }

            $recipientIds = array_values(array_unique(array_map(
                fn (array $contract) => (int) $contract['assignee_id'],
                $all
            )));
            $names = $this->resolveNames($recipientIds);
            $target = mb_strtolower(trim($recipientName));
            $matching = array_values(array_filter($all, function (array $contract) use ($names, $target): bool {
                $name = $names[(int) ($contract['assignee_id'] ?? 0)] ?? '';
                return mb_strtolower(trim($name)) === $target;
            }));

            usort($matching, fn (array $a, array $b) => strcmp(
                (string) ($b['date_issued'] ?? ''),
                (string) ($a['date_issued'] ?? '')
            ));

            $rows = array_map(function (array $contract) use ($character): array {
                $rawStatus = (string) ($contract['status'] ?? 'unknown');
                $completed = str_starts_with($rawStatus, 'finished');
                return [
                    'contract_id' => (int) ($contract['contract_id'] ?? 0),
                    'character_name' => $character->name,
                    'type' => (string) ($contract['type'] ?? 'unknown'),
                    'status' => $completed ? 'Completed' : 'Outstanding',
                    'raw_status' => $rawStatus,
                    'date_issued' => $contract['date_issued'] ?? null,
                    'date_completed' => $contract['date_completed'] ?? null,
                    'price' => (float) ($contract['price'] ?? 0),
                    'reward' => (float) ($contract['reward'] ?? 0),
                ];
            }, array_slice($matching, 0, max(1, $limit)));

            return ['status' => 'ready', 'message' => null, 'contracts' => $rows];
        } catch (Throwable $exception) {
            report($exception);
            return [
                'status' => 'error',
                'message' => 'EVE could not return contracts for your linked character just now. Please try again shortly.',
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
            ? ['assignee_id', 'acceptor_id']
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
