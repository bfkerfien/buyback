<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class RefreshJitaPrices extends Command
{
    protected $signature = 'buyback:refresh-jita-prices {type_ids?* : Optional EVE type IDs when refreshing selected items} {--all : Refresh every item with orders at Jita IV - Moon 4}';
    protected $description = 'Fetch best buy and sell orders at Jita IV - Moon 4 from EVE ESI.';

    public function handle(): int
    {
        $userAgent = (string) config('buyback.eve_user_agent');
        if ($userAgent === '') {
            $this->error('Set EVE_USER_AGENT in the environment before calling ESI.');
            return self::FAILURE;
        }

        $typeIds = array_values(array_unique(array_filter(
            array_map('intval', $this->argument('type_ids')),
            fn (int $id) => $id > 0
        )));

        if ($this->option('all')) {
            if ($typeIds) {
                $this->error('Use either --all or explicit type IDs, not both.');
                return self::FAILURE;
            }

            return $this->refreshAll();
        }

        if (!$typeIds) {
            $this->error('Supply type IDs or use --all to refresh every item traded at Jita 4-4.');
            return self::FAILURE;
        }

        $saved = 0;
        foreach ($typeIds as $typeId) {
            try {
                $prices = $this->fetchTypePrices($typeId);
                $this->storeSnapshots([[
                    'type_id' => $typeId,
                    'buy_price' => $prices['buy'],
                    'sell_price' => $prices['sell'],
                ]]);
                $saved++;
                $this->line("Type {$typeId}: buy ".($prices['buy'] ?? 'n/a').' / sell '.($prices['sell'] ?? 'n/a'));
            } catch (Throwable $exception) {
                $this->warn("Failed to refresh type {$typeId}: {$exception->getMessage()}");
            }
        }

        $this->info("Saved {$saved} Jita 4-4 price snapshots.");
        return $saved === count($typeIds) ? self::SUCCESS : self::FAILURE;
    }

    private function refreshAll(): int
    {
        $this->info('Fetching The Forge market orders and selecting orders located at Jita IV - Moon 4...');
        $http = Http::withHeaders(['User-Agent' => config('buyback.eve_user_agent'), 'X-Compatibility-Date' => config('buyback.esi_compatibility_date')])
            ->acceptJson()->timeout(45)->retry(3, 750);
        $regionId = (int) config('buyback.region_id', 10000002);
        $stationId = (int) config('buyback.jita_station_id', 60003760);
        $pages = 1;
        $prices = [];

        try {
            for ($page = 1; $page <= $pages; $page++) {
                $response = $http->get(
                    "https://esi.evetech.net/latest/markets/{$regionId}/orders/",
                    ['order_type' => 'all', 'page' => $page]
                )->throw();

                $pages = min(1000, max(1, (int) $response->header('X-Pages', 1)));
                foreach ($response->json() as $order) {
                    if ((int) ($order['location_id'] ?? 0) !== $stationId) {
                        continue;
                    }

                    $typeId = (int) ($order['type_id'] ?? 0);
                    $price = (float) ($order['price'] ?? 0);
                    if ($typeId < 1 || $price <= 0) {
                        continue;
                    }

                    $prices[$typeId] ??= ['buy' => null, 'sell' => null];
                    if (!empty($order['is_buy_order'])) {
                        $prices[$typeId]['buy'] = $prices[$typeId]['buy'] === null
                            ? $price : max($prices[$typeId]['buy'], $price);
                    } else {
                        $prices[$typeId]['sell'] = $prices[$typeId]['sell'] === null
                            ? $price : min($prices[$typeId]['sell'], $price);
                    }
                }

                $this->output->write('.');
                usleep(100000);
            }
        } catch (Throwable $exception) {
            $this->newLine();
            $this->error('ESI market refresh failed: '.$exception->getMessage());
            return self::FAILURE;
        }

        $rows = [];
        foreach ($prices as $typeId => $price) {
            $rows[] = ['type_id' => $typeId, 'buy_price' => $price['buy'], 'sell_price' => $price['sell']];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            $this->storeSnapshots($chunk);
        }

        $this->newLine();
        $this->info('Saved '.count($rows).' Jita 4-4 price snapshots from '.$pages.' market pages.');
        return $rows ? self::SUCCESS : self::FAILURE;
    }

    private function fetchTypePrices(int $typeId): array
    {
        $http = Http::withHeaders(['User-Agent' => config('buyback.eve_user_agent')])
            ->acceptJson()->timeout(30)->retry(3, 500);
        $regionId = (int) config('buyback.region_id', 10000002);
        $stationId = (int) config('buyback.jita_station_id', 60003760);
        $buy = null;
        $sell = null;
        $pages = 1;

        for ($page = 1; $page <= $pages; $page++) {
            $response = $http->get(
                "https://esi.evetech.net/latest/markets/{$regionId}/orders/",
                ['order_type' => 'all', 'type_id' => $typeId, 'page' => $page]
            )->throw();

            $pages = min(1000, max(1, (int) $response->header('X-Pages', 1)));
            foreach ($response->json() as $order) {
                if ((int) ($order['location_id'] ?? 0) !== $stationId) {
                    continue;
                }

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

        return ['buy' => $buy, 'sell' => $sell];
    }

    private function storeSnapshots(array $rows): void
    {
        $now = now();
        $records = array_map(fn (array $row) => [
            'type_id' => $row['type_id'],
            'region_id' => (string) config('buyback.region_id', '10000002'),
            'source' => 'esi-jita-4-4',
            'buy_price' => $row['buy_price'],
            'sell_price' => $row['sell_price'],
            'observed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows);

        if ($records) {
            DB::table('market_price_snapshots')->insert($records);
        }
    }
}
