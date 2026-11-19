<?php

namespace App\Services\Eve;

use App\Models\EveCharacter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class IncomingCharacterContractNotifier
{
    private const TARGET_CHARACTER_NAME = 'My Eve Online';
    private const CONTRACT_SCOPE = 'esi-contracts.read_character_contracts.v1';
    private const WEBHOOK_KEY = 'discord_contract_webhook';
    private const STARTED_AT_KEY = 'discord_contract_monitor_started_at';

    /**
     * Poll the personal-character contracts endpoint and notify about new
     * contracts assigned directly to the character named My Eve Online.
     *
     * @return array{status:string,message:string,checked?:int,notified?:int,failed?:int}
     */
    public function poll(): array
    {
        $encryptedWebhook = DB::table('buyback_system_status')
            ->where('status_key', self::WEBHOOK_KEY)
            ->value('status_value');

        if (!$encryptedWebhook) {
            return ['status' => 'disabled', 'message' => 'Discord contract notifications are not configured.'];
        }

        try {
            $webhook = Crypt::decryptString((string) $encryptedWebhook);
        } catch (Throwable $exception) {
            report($exception);
            return ['status' => 'configuration_error', 'message' => 'The saved Discord webhook could not be decrypted. Check APP_KEY.'];
        }

        $character = EveCharacter::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(self::TARGET_CHARACTER_NAME)])
            ->first();

        if (!$character) {
            return ['status' => 'missing_character', 'message' => 'Link the personal EVE character named My Eve Online to Buyback before enabling notifications.'];
        }

        $scopes = $character->scopes ?? [];
        if (is_string($scopes)) {
            $scopes = preg_split('/\s+/', $scopes, -1, PREG_SPLIT_NO_EMPTY);
        }
        if (!is_array($scopes) || !in_array(self::CONTRACT_SCOPE, $scopes, true)) {
            return ['status' => 'reauthorize', 'message' => 'My Eve Online must be re-authorized with esi-contracts.read_character_contracts.v1.'];
        }

        try {
            $token = $this->validAccessToken($character);
            $http = Http::withToken($token)
                ->withHeaders([
                    'User-Agent' => config('buyback.eve_user_agent'),
                    'X-Compatibility-Date' => config('buyback.esi_compatibility_date'),
                ])
                ->acceptJson()
                ->timeout(25)
                ->retry(2, 500);

            $incoming = [];
            $pages = 1;
            for ($page = 1; $page <= $pages; $page++) {
                $response = $http->get(
                    'https://esi.evetech.net/latest/characters/' . (int) $character->eve_character_id . '/contracts/',
                    ['page' => $page]
                )->throw();

                $pages = min(100, max(1, (int) $response->header('X-Pages', 1)));

                foreach ($response->json() ?? [] as $contract) {
                    $assigneeId = (int) ($contract['assignee_id'] ?? 0);
                    $issuerId = (int) ($contract['issuer_id'] ?? 0);
                    $status = (string) ($contract['status'] ?? '');

                    // Personal character endpoint only; the contract must be
                    // assigned to My Eve Online, not issued by that character.
                    if ($assigneeId !== (int) $character->eve_character_id || $issuerId === (int) $character->eve_character_id) {
                        continue;
                    }
                    if (!in_array($status, ['outstanding', 'in_progress'], true)) {
                        continue;
                    }

                    $incoming[] = $contract;
                }
            }

            $issuerIds = array_values(array_unique(array_filter(array_map(
                fn (array $contract) => (int) ($contract['issuer_id'] ?? 0),
                $incoming
            ))));
            $issuerNames = $this->resolveNames($issuerIds);

            $startedAtValue = DB::table('buyback_system_status')
                ->where('status_key', self::STARTED_AT_KEY)
                ->value('status_value');

            if (!$startedAtValue) {
                $startedAtValue = now()->toIso8601String();
                DB::table('buyback_system_status')->updateOrInsert(
                    ['status_key' => self::STARTED_AT_KEY],
                    ['status_value' => $startedAtValue, 'created_at' => now(), 'updated_at' => now()]
                );
            }
            $startedAt = Carbon::parse($startedAtValue);

            $notified = 0;
            $failed = 0;

            foreach ($incoming as $contract) {
                $contractId = (int) ($contract['contract_id'] ?? 0);
                if ($contractId < 1) {
                    continue;
                }

                $issuedAt = !empty($contract['date_issued'])
                    ? Carbon::parse($contract['date_issued'])
                    : null;
                $existing = DB::table('eve_contract_notification_logs')
                    ->where('eve_character_id', $character->id)
                    ->where('contract_id', $contractId)
                    ->first();

                if ($existing && in_array($existing->notification_status, ['sent', 'ignored'], true)) {
                    continue;
                }

                $contractData = json_encode($contract, JSON_THROW_ON_ERROR);
                if (!$issuedAt || $issuedAt->lt($startedAt)) {
                    if (!$existing) {
                        DB::table('eve_contract_notification_logs')->insert([
                            'eve_character_id' => $character->id,
                            'contract_id' => $contractId,
                            'issuer_id' => (int) ($contract['issuer_id'] ?? 0) ?: null,
                            'contract_type' => $contract['type'] ?? null,
                            'contract_status' => $contract['status'] ?? null,
                            'date_issued' => $issuedAt,
                            'notification_status' => 'ignored',
                            'contract_data' => $contractData,
                            'first_seen_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    continue;
                }

                if (!$existing) {
                    DB::table('eve_contract_notification_logs')->insert([
                        'eve_character_id' => $character->id,
                        'contract_id' => $contractId,
                        'issuer_id' => (int) ($contract['issuer_id'] ?? 0) ?: null,
                        'contract_type' => $contract['type'] ?? null,
                        'contract_status' => $contract['status'] ?? null,
                        'date_issued' => $issuedAt,
                        'notification_status' => 'pending',
                        'contract_data' => $contractData,
                        'first_seen_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('eve_contract_notification_logs')
                        ->where('id', $existing->id)
                        ->update([
                            'notification_status' => 'pending',
                            'contract_status' => $contract['status'] ?? null,
                            'contract_data' => $contractData,
                            'last_error' => null,
                            'updated_at' => now(),
                        ]);
                }

                $issuerId = (int) ($contract['issuer_id'] ?? 0);
                $issuerName = $issuerNames[$issuerId] ?? ('EVE entity #' . $issuerId);

                try {
                    $this->sendDiscordNotification($webhook, $character, $contract, $issuerName);
                    DB::table('eve_contract_notification_logs')
                        ->where('eve_character_id', $character->id)
                        ->where('contract_id', $contractId)
                        ->update([
                            'notification_status' => 'sent',
                            'notified_at' => now(),
                            'last_error' => null,
                            'updated_at' => now(),
                        ]);
                    $notified++;
                } catch (Throwable $exception) {
                    Log::warning('Discord contract webhook delivery failed.', [
                        'contract_id' => $contractId,
                        'exception' => class_basename($exception),
                    ]);
                    DB::table('eve_contract_notification_logs')
                        ->where('eve_character_id', $character->id)
                        ->where('contract_id', $contractId)
                        ->update([
                            'notification_status' => 'failed',
                            'last_error' => 'Discord delivery failed (' . class_basename($exception) . ').',
                            'updated_at' => now(),
                        ]);
                    $failed++;
                }
            }

            return [
                'status' => $failed > 0 ? 'partial' : 'ok',
                'message' => 'Checked ' . count($incoming) . ' incoming personal contract(s); sent ' . $notified . ' Discord notification(s); ' . $failed . ' failed.',
                'checked' => count($incoming),
                'notified' => $notified,
                'failed' => $failed,
            ];
        } catch (Throwable $exception) {
            report($exception);
            return ['status' => 'error', 'message' => 'EVE incoming-contract polling failed: ' . $exception->getMessage()];
        }
    }

    private function sendDiscordNotification(string $webhook, EveCharacter $character, array $contract, string $issuerName): void
    {
        $fields = [
            ['name' => 'Character', 'value' => $character->name, 'inline' => true],
            ['name' => 'Issuer', 'value' => mb_substr($issuerName, 0, 200), 'inline' => true],
            ['name' => 'Contract type', 'value' => ucfirst(str_replace('_', ' ', (string) ($contract['type'] ?? 'unknown'))), 'inline' => true],
            ['name' => 'Contract ID', 'value' => (string) ($contract['contract_id'] ?? 'Unknown'), 'inline' => true],
            ['name' => 'Expires', 'value' => $this->formatDate($contract['date_expired'] ?? null), 'inline' => true],
            ['name' => 'Price', 'value' => $this->formatIsk($contract['price'] ?? 0), 'inline' => true],
            ['name' => 'Reward', 'value' => $this->formatIsk($contract['reward'] ?? 0), 'inline' => true],
            ['name' => 'Collateral', 'value' => $this->formatIsk($contract['collateral'] ?? 0), 'inline' => true],
        ];

        if (isset($contract['buyout']) && (float) $contract['buyout'] > 0) {
            $fields[] = ['name' => 'Buyout', 'value' => $this->formatIsk($contract['buyout']), 'inline' => true];
        }

        $description = trim((string) ($contract['title'] ?? ''));
        $details = trim((string) ($contract['description'] ?? ''));
        if ($details !== '') {
            $description .= ($description !== '' ? "\n" : '') . $details;
        }
        if ($description === '') {
            $description = 'A new personal EVE contract was assigned to My Eve Online.';
        }

        Http::asJson()->withoutRedirecting()->timeout(10)->post($webhook, [
            'embeds' => [[
                'title' => 'New EVE Contract Received',
                'description' => mb_substr($description, 0, 3500),
                'color' => 0x62D9E9,
                'fields' => $fields,
                'timestamp' => now()->toIso8601String(),
                'footer' => ['text' => 'EveBuyback · Personal character contracts'],
            ]],
        ])->throw();
    }

    private function formatIsk(mixed $value): string
    {
        return number_format((float) $value, 2) . ' ISK';
    }

    private function formatDate(mixed $value): string
    {
        if (!$value) {
            return 'Not provided';
        }

        try {
            return Carbon::parse($value)->timezone(config('app.timezone'))->format('M j, Y g:i A T');
        } catch (Throwable) {
            return (string) $value;
        }
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
            ])->acceptJson()->timeout(10)
                ->post('https://esi.evetech.net/latest/universe/names/', $ids)
                ->throw()->json();

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

    private function validAccessToken(EveCharacter $character): string
    {
        if ($character->access_token && $character->token_expires_at && $character->token_expires_at->isFuture()) {
            return $character->access_token;
        }

        if (!$character->refresh_token) {
            throw new RuntimeException('My Eve Online must be re-authorized through EVE SSO.');
        }

        $clientId = (string) config('services.eve.client_id');
        $clientSecret = (string) config('services.eve.client_secret');
        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('EVE SSO client credentials are not configured.');
        }

        $response = Http::asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->post('https://login.eveonline.com/v2/oauth/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $character->refresh_token,
            ])->throw()->json();

        $character->forceFill([
            'access_token' => $response['access_token'],
            'refresh_token' => $response['refresh_token'] ?? $character->refresh_token,
            'token_expires_at' => now()->addSeconds((int) ($response['expires_in'] ?? 1200)),
        ])->save();

        return (string) $response['access_token'];
    }
}
