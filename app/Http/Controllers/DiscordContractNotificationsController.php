<?php

namespace App\Http\Controllers;

use App\Models\EveCharacter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class DiscordContractNotificationsController extends Controller
{
    private const WEBHOOK_KEY = 'discord_contract_webhook';
    private const STARTED_AT_KEY = 'discord_contract_monitor_started_at';
    private const TARGET_CHARACTER_NAME = 'My Eve Online';
    private const CONTRACT_SCOPE = 'esi-contracts.read_character_contracts.v1';

    public function index(): View
    {
        $character = EveCharacter::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(self::TARGET_CHARACTER_NAME)])
            ->first();

        $scopes = $character?->scopes ?? [];
        if (is_string($scopes)) {
            $scopes = preg_split('/\s+/', $scopes, -1, PREG_SPLIT_NO_EMPTY);
        }

        return view('admin.discord-contract-notifications', [
            'character' => $character,
            'hasContractScope' => is_array($scopes) && in_array(self::CONTRACT_SCOPE, $scopes, true),
            'webhookConfigured' => (bool) DB::table('buyback_system_status')->where('status_key', self::WEBHOOK_KEY)->value('status_value'),
            'monitorStartedAt' => DB::table('buyback_system_status')->where('status_key', self::STARTED_AT_KEY)->value('status_value'),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        if ($request->boolean('clear_webhook')) {
            DB::table('buyback_system_status')->whereIn('status_key', [self::WEBHOOK_KEY, self::STARTED_AT_KEY])->delete();
            $this->audit($request, 'discord_contract_webhook.cleared');
            return back()->with('status', 'Discord contract notifications have been disabled.');
        }

        $data = $request->validate([
            'webhook_url' => [
                'required',
                'string',
                'url',
                'regex:/^https:\/\/(?:discord\.com|discordapp\.com)\/api\/webhooks\/[0-9]+\/[A-Za-z0-9._-]+(?:\?.*)?$/i',
            ],
        ]);

        $oldEncrypted = DB::table('buyback_system_status')
            ->where('status_key', self::WEBHOOK_KEY)
            ->value('status_value');
        $changed = true;
        if ($oldEncrypted) {
            try {
                $changed = !hash_equals(Crypt::decryptString((string) $oldEncrypted), $data['webhook_url']);
            } catch (Throwable) {
                $changed = true;
            }
        }

        DB::table('buyback_system_status')->updateOrInsert(
            ['status_key' => self::WEBHOOK_KEY],
            ['status_value' => Crypt::encryptString($data['webhook_url']), 'created_at' => now(), 'updated_at' => now()]
        );

        if ($changed || !DB::table('buyback_system_status')->where('status_key', self::STARTED_AT_KEY)->exists()) {
            DB::table('buyback_system_status')->updateOrInsert(
                ['status_key' => self::STARTED_AT_KEY],
                ['status_value' => now()->toIso8601String(), 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $this->audit($request, 'discord_contract_webhook.saved');
        return back()->with('status', 'Discord webhook saved. New personal contracts received after monitoring started will trigger notifications.');
    }

    public function test(Request $request): RedirectResponse
    {
        $encryptedWebhook = DB::table('buyback_system_status')
            ->where('status_key', self::WEBHOOK_KEY)
            ->value('status_value');

        if (!$encryptedWebhook) {
            return back()->withErrors(['webhook_url' => 'Save a Discord webhook URL before sending a test notification.']);
        }

        try {
            $webhook = Crypt::decryptString((string) $encryptedWebhook);
            Http::asJson()->withoutRedirecting()->timeout(10)->post($webhook, [
                'content' => 'EveBuyback Discord test: notifications for personal contracts received by My Eve Online are connected.',
            ])->throw();
        } catch (Throwable $exception) {
            Log::warning('Discord webhook test failed.', ['exception' => class_basename($exception)]);
            return back()->withErrors(['webhook_url' => 'Discord test notification failed. Check the webhook URL and try again.']);
        }

        $this->audit($request, 'discord_contract_webhook.tested');
        return back()->with('status', 'Test notification sent to Discord.');
    }

    private function audit(Request $request, string $action): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => 'discord_contract_webhook',
            'subject_id' => null,
            'details' => json_encode(['target_character' => self::TARGET_CHARACTER_NAME]),
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
