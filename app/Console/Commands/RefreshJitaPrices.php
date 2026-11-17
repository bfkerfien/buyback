<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class RefreshJitaPrices extends Command
{
    protected $signature = 'buyback:refresh-jita-prices {type_ids?* : Optional EVE type IDs when refreshing selected items} {--all : Refresh every item with orders at Jita IV - Moon 4} {--pi : Refresh every input and output type used by imported PI schematics} {--fast : Refresh compressed ore, ice, gas, minerals, and all PI recipe items} {--ships : Refresh all published ship hulls}';
    protected $description = 'Fetch Jita IV - Moon 4 market order depth and prices from EVE ESI.';

    public function handle(): int
    {
        if ((string) config('buyback.eve_user_agent') === '') {
            $this->error('Set EVE_USER_AGENT in the environment before calling ESI.');
            return self::FAILURE;
        }

        $typeIds = array_values(array_unique(array_filter(array_map('intval', $this->argument('type_ids')), fn (int $id) => $id > 0)));

        $modeCount = (int) $this->option('all') + (int) $this->option('pi') + (int) $this->option('fast') + (int) $this->option('ships');
        if ($modeCount > 1 || ($typeIds && $modeCount > 0)) {
            $this->error('Choose one refresh mode or provide explicit type IDs.');
            return self::FAILURE;
        }

        if ($this->option('fast')) {
            return $this->refreshTypeIds($this->fastRefreshTypeIds(), '15-minute market set');
        }

        if ($this->option('ships')) {
            $shipIds = DB::table('eve_item_types as t')
                ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
                ->where('t.published', true)
                ->where('g.category_name', 'Ship')
                ->orderBy('t.type_id')
                ->pluck('t.type_id')->map(fn ($id) => (int) $id)->all();
            return $this->refreshTypeIds($shipIds, '2-hour ship hull set');
        }
        if ($this->option('pi')) {
            if ($this->option('all') || $typeIds) {
                $this->error('Use --pi by itself, without --all or explicit type IDs.');
                return self::FAILURE;
            }
            $typeIds = array_merge(
                DB::table('eve_planet_schematics')->pluck('product_type_id')->all(),
                DB::table('eve_planet_schematic_inputs')->pluck('type_id')->all()
            );
            $typeIds = array_values(array_unique(array_filter(array_map('intval', $typeIds), fn (int $id) => $id > 0)));
            if (!$typeIds) {
                $this->error('No PI recipe types are imported yet.');
                return self::FAILURE;
            }
        }

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

        return $this->refreshTypeIds($typeIds, 'explicit type list');
    }

    private function fastRefreshTypeIds(): array
    {
        $compressedOreIds = DB::table('eve_item_types as t')
            ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->where('t.published', true)
            ->whereIn('g.category_name', ['Asteroid', 'Moon Materials'])
            ->where('t.type_name', 'like', 'Compressed %')
            ->pluck('t.type_id')->all();

        $iceTypeIds = DB::table('eve_item_types as t')
            ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->where('t.published', true)
            ->whereIn('g.group_name', ['Ice', 'Compressed Ice'])
            ->pluck('t.type_id')->all();
        $iceProductIds = $iceTypeIds
            ? DB::table('eve_type_materials')->whereIn('type_id', $iceTypeIds)->pluck('material_type_id')->all()
            : [];

        $gasTypeIds = DB::table('eve_item_types as t')
            ->leftJoin('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->where('t.published', true)
            ->where(function ($query) {
                $query->where('g.group_name', 'Compressed Gas')
                    ->orWhere('t.type_name', 'like', '%Mykoserocin')
                    ->orWhere('t.type_name', 'like', '%Cytoserocin');
            })
            ->whereNotIn('t.type_name', ['Chartreuse Cytoserocin', 'Gamboge Cytoserocin'])
            ->pluck('t.type_id')->all();

        $mineralIds = DB::table('eve_item_types as t')
            ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->where('t.published', true)
            ->where('g.group_name', 'Mineral')
            ->pluck('t.type_id')->all();

        $piIds = array_merge(
            DB::table('eve_planet_schematics')->pluck('product_type_id')->all(),
            DB::table('eve_planet_schematic_inputs')->pluck('type_id')->all()
        );

        return array_values(array_unique(array_filter(array_map('intval', array_merge(
            $compressedOreIds, $iceTypeIds, $iceProductIds, $gasTypeIds, $mineralIds, $piIds
        )), fn (int $id) => $id > 0)));
    }

    private function refreshTypeIds(array $typeIds, string $label): int
    {
        $typeIds = array_values(array_unique(array_filter(array_map('intval', $typeIds), fn (int $id) => $id > 0)));
        if (!$typeIds) {
            $this->error('No item types found for ' . $label . '. Check the imported EVE SDE and PI schematics.');
            return self::FAILURE;
        }
        $saved = 0;
        $failed = 0;
        foreach ($typeIds as $typeId) {
            try {
                $orders = $this->fetchTypeOrders($typeId);
                if (!$orders) {
                    $this->warn('Type ' . $typeId . ': no Jita IV - Moon 4 orders.');
                    continue;
                }
                // These scheduled refreshes only need current best-price snapshots.
                // Do not append the full order book every 15 minutes / 2 hours.
                $this->storeBestPriceSnapshot($typeId, $orders);
                $saved++;
            } catch (Throwable $exception) {
                $failed++;
                $this->warn('Failed to refresh type ' . $typeId . ': ' . $exception->getMessage());
            }
        }
        $this->info($label . ': saved prices for ' . $saved . ' of ' . count($typeIds) . ' types; ' . $failed . ' failed. Order-book history was skipped.');
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function refreshAll(): int
    {
        $this->info('Fetching The Forge market orders and retaining all orders located at Jita IV - Moon 4...');
        $http = Http::withHeaders([
            'User-Agent' => config('buyback.eve_user_agent'),
            'X-Compatibility-Date' => config('buyback.esi_compatibility_date'),
        ])->acceptJson()->timeout(45)->retry(3, 750);

        $regionId = (int) config('buyback.region_id', 10000002);
        $stationId = (int) config('buyback.jita_station_id', 60003760);
        $pages = 1;
        $orders = [];
        $prices = [];

        try {
            for ($page = 1; $page <= $pages; $page++) {
                $response = $http->get("https://esi.evetech.net/latest/markets/{$regionId}/orders/", [
                    'order_type' => 'all',
                    'page' => $page,
                ])->throw();

                $pages = min(1000, max(1, (int) $response->header('X-Pages', 1)));
                foreach ($response->json() as $order) {
                    if ((int) ($order['location_id'] ?? 0) !== $stationId) continue;
                    $typeId = (int) ($order['type_id'] ?? 0);
                    $price = (float) ($order['price'] ?? 0);
                    $volume = (int) ($order['volume_remain'] ?? 0);
                    if ($typeId < 1 || $price <= 0 || $volume < 1) continue;

                    $isBuy = (bool) ($order['is_buy_order'] ?? false);
                    $orders[] = [
                        'type_id' => $typeId,
                        'region_id' => (string) $regionId,
                        'location_id' => $stationId,
                        'is_buy_order' => $isBuy,
                        'price' => $price,
                        'volume_remain' => $volume,
                    ];
                    $prices[$typeId] ??= ['buy' => null, 'sell' => null];
                    if ($isBuy) {
                        $prices[$typeId]['buy'] = $prices[$typeId]['buy'] === null ? $price : max($prices[$typeId]['buy'], $price);
                    } else {
                        $prices[$typeId]['sell'] = $prices[$typeId]['sell'] === null ? $price : min($prices[$typeId]['sell'], $price);
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

        if (!$orders) {
            $this->newLine();
            $this->error('No Jita IV - Moon 4 orders found; refresh was not recorded as successful.');
            return self::FAILURE;
        }

        $this->storeOrderBook($orders);
        $snapshots = [];
        foreach ($prices as $typeId => $price) {
            $snapshots[] = ['type_id' => $typeId, 'buy_price' => $price['buy'], 'sell_price' => $price['sell']];
        }
        foreach (array_chunk($snapshots, 500) as $chunk) $this->storeSnapshots($chunk);

        $refreshedAt = now();
        foreach ([
            'jita_full_refresh_at' => $refreshedAt->toDateTimeString(),
            'jita_full_refresh_items' => (string) count($prices),
        ] as $key => $value) {
            DB::table('buyback_system_status')->updateOrInsert(
                ['status_key' => $key],
                ['status_value' => $value, 'created_at' => $refreshedAt, 'updated_at' => $refreshedAt]
            );
        }

        $this->newLine();
        $this->info('Saved '.count($orders).' Jita IV - Moon 4 orders covering '.count($prices).' item types across '.$pages.' market pages.');
        return self::SUCCESS;
    }

    private function fetchTypeOrders(int $typeId): array
    {
        $http = Http::withHeaders([
            'User-Agent' => config('buyback.eve_user_agent'),
            'X-Compatibility-Date' => config('buyback.esi_compatibility_date'),
        ])->acceptJson()->timeout(30)->retry(3, 500);

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
                    'type_id' => $typeId,
                    'region_id' => (string) $regionId,
                    'location_id' => $stationId,
                    'is_buy_order' => (bool) ($order['is_buy_order'] ?? false),
                    'price' => $price,
                    'volume_remain' => $volume,
                ];
            }
            usleep(100000);
        }
        return $orders;
    }

    private function storeOrderBook(array $orders): void
    {
        $now = now();
        foreach (array_chunk($orders, 500) as $chunk) {
            $records = array_map(fn (array $order) => $order + [
                'observed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk);
            DB::table('market_order_book_snapshots')->insert($records);
        }
    }

    private function storeBestPriceSnapshot(int $typeId, array $orders): void
    {
        $buy = null;
        $sell = null;
        foreach ($orders as $order) {
            if ($order['is_buy_order']) {
                $buy = $buy === null ? $order['price'] : max($buy, $order['price']);
            } else {
                $sell = $sell === null ? $order['price'] : min($sell, $order['price']);
            }
        }
        $this->storeSnapshots([['type_id' => $typeId, 'buy_price' => $buy, 'sell_price' => $sell]]);
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
        if ($records) DB::table('market_price_snapshots')->insert($records);
    }
}
