<?php

namespace Tests\Feature;

use App\Models\EveCharacter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiscordContractNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_new_personal_contracts_assigned_to_my_eve_online_are_sent_to_discord_once(): void
    {
        $user = User::create([
            'name' => 'Admin Pilot',
            'role' => 'admin',
            'active' => true,
        ]);

        $character = EveCharacter::create([
            'user_id' => $user->id,
            'eve_character_id' => 123456,
            'name' => 'My Eve Online',
            'owner_hash' => 'test-owner-hash',
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'token_expires_at' => now()->addHour(),
            'scopes' => ['esi-contracts.read_character_contracts.v1'],
        ]);

        DB::table('buyback_system_status')->insert([
            'status_key' => 'discord_contract_webhook',
            'status_value' => Crypt::encryptString('https://discord.com/api/webhooks/123456/test-token'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('buyback_system_status')->insert([
            'status_key' => 'discord_contract_monitor_started_at',
            'status_value' => now()->subMinutes(5)->toIso8601String(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $webhookCalls = 0;
        Http::fake([
            'https://esi.evetech.net/latest/characters/123456/contracts/*' => Http::response([
                [
                    'contract_id' => 9001,
                    'issuer_id' => 654321,
                    'assignee_id' => 123456,
                    'type' => 'item_exchange',
                    'status' => 'outstanding',
                    'date_issued' => now()->subMinute()->toIso8601String(),
                    'date_expired' => now()->addDays(2)->toIso8601String(),
                    'title' => 'Test contract',
                    'description' => 'Personal contract for My Eve Online.',
                    'price' => 1000000,
                    'reward' => 0,
                    'collateral' => 0,
                ],
                [
                    'contract_id' => 9002,
                    'issuer_id' => 654322,
                    'assignee_id' => 999999,
                    'type' => 'item_exchange',
                    'status' => 'outstanding',
                    'date_issued' => now()->subMinute()->toIso8601String(),
                ],
            ], 200, ['X-Pages' => '1']),
            'https://esi.evetech.net/latest/universe/names/' => Http::response([
                ['id' => 654321, 'name' => 'Contract Issuer'],
            ], 200),
            'https://discord.com/api/webhooks/*' => function ($request) use (&$webhookCalls) {
                $webhookCalls++;
                return Http::response('', 204);
            },
        ]);

        Artisan::call('buyback:notify-incoming-contracts');
        Artisan::call('buyback:notify-incoming-contracts');

        $this->assertDatabaseHas('eve_contract_notification_logs', [
            'eve_character_id' => $character->id,
            'contract_id' => 9001,
            'notification_status' => 'sent',
        ]);
        $this->assertDatabaseMissing('eve_contract_notification_logs', [
            'eve_character_id' => $character->id,
            'contract_id' => 9002,
        ]);
        $this->assertSame(1, $webhookCalls);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/characters/123456/contracts/'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/corporations/'));
    }

    public function test_discord_alert_settings_page_is_admin_only(): void
    {
        $member = User::create([
            'name' => 'Member Pilot',
            'role' => 'member',
            'active' => true,
        ]);

        $this->actingAs($member)
            ->get('/admin/discord-contract-notifications')
            ->assertForbidden();
    }
}
