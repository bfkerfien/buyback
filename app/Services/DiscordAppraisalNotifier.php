<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class DiscordAppraisalNotifier
{
    /**
     * @param array{token:string, kind:string, payout_value:float|int|string, origin_system:?string, character_name:?string, item_count:int} $appraisal
     */
    public function send(array $appraisal): void
    {
        $webhook = (string) config('services.discord.appraisal_webhook');

        if ($webhook === '') {
            return;
        }

        $title = $appraisal['kind'] === 'ship' ? 'New ship buyback appraisal' : 'New inventory appraisal';
        $characterName = $appraisal['character_name'] ?: 'Guest appraisal';
        $origin = $appraisal['origin_system'] ?: 'Not specified';

        $response = Http::asJson()
            ->withoutRedirecting()
            ->timeout(10)
            ->post($webhook, [
                'content' => 'A new EveBuyback appraisal was created.',
                'allowed_mentions' => ['parse' => []],
                'embeds' => [[
                    'title' => $title,
                    'url' => route('appraisals.show', $appraisal['token'], true),
                    'color' => 6540797,
                    'fields' => [
                        ['name' => 'Character', 'value' => mb_substr($characterName, 0, 256), 'inline' => true],
                        ['name' => 'Estimated buyback', 'value' => number_format((float) $appraisal['payout_value'], 2) . ' ISK', 'inline' => true],
                        ['name' => 'Origin', 'value' => mb_substr($origin, 0, 256), 'inline' => true],
                        ['name' => 'Items', 'value' => (string) $appraisal['item_count'], 'inline' => true],
                    ],
                    'timestamp' => now()->toIso8601String(),
                ]],
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('Discord rejected an appraisal notification with HTTP ' . $response->status() . '.');
        }
    }
}
