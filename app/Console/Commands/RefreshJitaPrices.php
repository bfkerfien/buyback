<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class RefreshJitaPrices extends Command
{
    protected $signature = 'buyback:refresh-jita-prices {type_ids* : One or more EVE type IDs to refresh}';
    protected $description = 'Fetch current Jita best buy and sell prices from EVE ESI for selected item types.';

    public function handle(): int
    {
        $typeIds = array_values(array_unique(array_filter(
            array_map('intval', $this->argument('type_ids')),
            fn (int $id) => $id > 0
        )));

        if (!$typeIds) {
            $this->error('Supply at least one valid EVE type ID.');
            return self::FAILURE;
        }

        $userAgent = (string) config('buyback.eve_user_agent');
        if ($userAgent === '') {
            $this->error('Set EVE_USER_AGENT in the environment before calling ESI.');
            return self::FAILURE;
        }

        $http = Http::withHeaders(['User-Agent' => $userAgent])
            ->acceptJson()
            ->timeout(30)
            ->retry(3, 500);

        $saved = 0;

        foreach ($typeIds as $typeId) {
            try {
                $buy = null;
                $sell = null;
                $pages = 1;

                for ($page = 1; $page <= $pages; $page++) {
                    $response = $http->get(
                        'https://esi.evetech.net/latest/markets/10000002/orders/',
                        ['order_type' => 'all', 'type_id' => $typeId, 'page' => $page]
                    );

                    if (!$response->successful()) {
                        $this->warn("ESI returned HTTP {$response->status()} for type {$typeId}, page {$page}.");
                        continue 2;
                    }

                    $pages = min(20, max(1, (int) $response->header('X-Pages', 1)));

                    foreach ($response->json() as $order) {
                        $price = (float) ($order['price'] ?? 0);
                        if ($price <= 0) {
                            continue;
                        }

                        if (!empty($order['is_buy_order'])) {
                            $buy = $buy === null ? $price : max($buy, $price);
                        } else {
                            $sell = $sell === null ? $price : min($sell, $price);
                        }
                    }

                    usleep(100000);
                }

                DB::table('market_price_snapshots')->insert([
                    'type_id' => $typeId,
                    'region_id' => '10000002',
                    'source' => 'esi-jita',
                    'buy_price' => $buy,
                    'sell_price' => $sell,
                    'observed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $saved++;
                $this->line("Type {$typeId}: buy " . ($buy ?? 'n/a') . ' / sell ' . ($sell ?? 'n/a'));
            } catch (Throwable $exception) {
                $this->warn("Failed to refresh type {$typeId}: {$exception->getMessage()}");
            }
        }

        $this->info("Saved {$saved} Jita price snapshots.");
        return $saved === count($typeIds) ? self::SUCCESS : self::FAILURE;
    }
}
