<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

final class JitaMarketPriceRefresher
{
    public function ensureCurrentPrices(array $typeIds, string $side = 'buy'): void
    {
        $typeIds = $this->normalizeTypeIds($typeIds);
        if (!$typeIds) {
            return;
        }
        $side = $side === 'sell' ? 'sell' : 'buy';
        $priceColumn = $side . '_price';
        $snapshotColumn = $side . '_snapshot_id';

        $regionId = (string) config('buyback.region_id', '10000002');
        $now = now();
        DB::table('market_price_watchlist')->insertOrIgnore(array_map(fn (int $typeId) => [
            'region_id' => $regionId,
            'type_id' => $typeId,
            'added_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], $typeIds));

        $pricedIds = DB::table('market_price_current')
            ->where('region_id', $regionId)
            ->whereIn('type_id', $typeIds)
            ->whereNotNull($snapshotColumn)
            ->where($priceColumn, '>', 0)
            ->distinct()
            ->pluck('type_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->refreshTypes(array_values(array_diff($typeIds, $pricedIds)));
    }

    /** @return array{saved:int,failed:int} */
    public function refreshTypes(array $typeIds): array
    {
        $typeIds = $this->normalizeTypeIds($typeIds);
        $saved = 0;
        $failed = 0;

        foreach ($typeIds as $typeId) {
            try {
                $orders = $this->fetchTypeOrders($typeId);
                if ($orders) {
                    $buy = null;
                    $sell = null;
                    foreach ($orders as $order) {
                        if ($order['is_buy_order']) {
                            $buy = $buy === null ? $order['price'] : max($buy, $order['price']);
                        } else {
                            $sell = $sell === null ? $order['price'] : min($sell, $order['price']);
                        }
                    }

                    $now = now();
                    app(MarketPriceSnapshotWriter::class)->store([[
                        'type_id' => $typeId,
                        'region_id' => (string) config('buyback.region_id', '10000002'),
                        'source' => 'esi-jita-4-4',
                        'buy_price' => $buy,
                        'sell_price' => $sell,
                        'observed_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]]);
                    $saved++;
                }
            } catch (Throwable) {
                $failed++;
            }

            DB::table('market_price_watchlist')
                ->where('region_id', (string) config('buyback.region_id', '10000002'))
                ->where('type_id', $typeId)
                ->update(['last_checked_at' => now(), 'updated_at' => now()]);
        }

        return ['saved' => $saved, 'failed' => $failed];
    }

    private function fetchTypeOrders(int $typeId): array
    {
        $http = Http::withHeaders([
            'User-Agent' => config('buyback.eve_user_agent'),
            'X-Compatibility-Date' => config('buyback.esi_compatibility_date'),
        ])->acceptJson()->timeout(10)->retry(2, 250);

        $regionId = (int) config('buyback.region_id', 10000002);
        $stationId = (int) config('buyback.jita_station_id', 60003760);
        $pages = 1;
        $orders = [];
        for ($page = 1; $page <= $pages; $page++) {
            $response = $http->get("https://esi.evetech.net/latest/markets/{$regionId}/orders/", [
                'order_type' => 'all',
                'type_id' => $typeId,
                'page' => $page,
            ])->throw();
            $pages = min(1000, max(1, (int) $response->header('X-Pages', 1)));
            foreach ($response->json() as $order) {
                if ((int) ($order['location_id'] ?? 0) !== $stationId) continue;
                $price = (float) ($order['price'] ?? 0);
                $volume = (int) ($order['volume_remain'] ?? 0);
                if ($price <= 0 || $volume < 1) continue;
                $orders[] = [
                    'is_buy_order' => (bool) ($order['is_buy_order'] ?? false),
                    'price' => $price,
                ];
            }
            usleep(100000);
        }

        return $orders;
    }

    /** @return array<int, int> */
    private function normalizeTypeIds(array $typeIds): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $typeIds), fn (int $id) => $id > 0)));
    }
}
