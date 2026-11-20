<?php

namespace App\Console\Commands;

use App\Services\MarketPriceSnapshotWriter;
use App\Services\JitaMarketPriceRefresher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class RefreshJitaPrices extends Command
{
    protected $signature = 'buyback:refresh-jita-prices {type_ids?* : Optional EVE type IDs when refreshing selected items} {--all : Refresh every item with orders at Jita IV - Moon 4} {--pi : Refresh every input and output type used by imported PI schematics} {--fast : Refresh compressed asteroid ores, compressed ice, selected ice products, eight minerals, and all PI recipe items} {--ships : Refresh all published ship hulls}';
    protected $description = 'Fetch Jita IV - Moon 4 market order depth and prices from EVE ESI.';

    public function handle(JitaMarketPriceRefresher $refresher): int
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
            $regionId = (string) config('buyback.region_id', '10000002');
            $baseTypeIds = $this->fastRefreshTypeIds();
            $now = now();
            DB::table('market_price_watchlist')->insertOrIgnore(array_map(fn (int $typeId) => [
                'region_id' => $regionId,
                'type_id' => $typeId,
                'added_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ], $baseTypeIds));
            $watchedTypeIds = DB::table('market_price_watchlist')
                ->where('region_id', $regionId)->pluck('type_id')->map(fn ($id) => (int) $id)->all();
            return $this->refreshTypeIds(array_merge($baseTypeIds, $watchedTypeIds), '15-minute appraisal and core market set', $refresher);
        }

        if ($this->option('ships')) {
            $shipIds = DB::table('eve_item_types as t')
                ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
                ->where('t.published', true)
                ->where('g.category_name', 'Ship')
                ->orderBy('t.type_id')
                ->pluck('t.type_id')->map(fn ($id) => (int) $id)->all();
            return $this->refreshTypeIds($shipIds, '2-hour ship hull set', $refresher);
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

        return $this->refreshTypeIds($typeIds, 'explicit type list', $refresher);
    }

    private function fastRefreshTypeIds(): array
    {
        // Only compressed asteroid ores are refreshed here. The SDE name prefix
        // includes every published bonus-grade variant (II/III/IV, etc.).
        $compressedOreIds = DB::table('eve_item_types as t')
            ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->where('t.published', true)
            ->where('g.category_name', 'Asteroid')
            ->where('t.type_name', 'like', 'Compressed %')
            ->pluck('t.type_id')->all();

        // Do not pull raw ice types or every possible refining output.
        $compressedIceIds = DB::table('eve_item_types as t')
            ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->where('t.published', true)
            ->where('g.group_name', 'Compressed Ice')
            ->where('t.type_name', 'like', 'Compressed %')
            ->pluck('t.type_id')->all();

        $iceProductIds = DB::table('eve_item_types')
            ->where('published', true)
            ->whereIn('type_name', [
                'Nitrogen Isotopes',
                'Strontium Clathrates',
                'Heavy Water',
                'Liquid Ozone',
            ])
            ->pluck('type_id')->all();

        // Exactly the eight standard asteroid-reprocessing minerals.
        $mineralIds = DB::table('eve_item_types as t')
            ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->where('t.published', true)
            ->where('g.group_name', 'Mineral')
            ->whereIn('t.type_name', [
                'Tritanium', 'Pyerite', 'Mexallon', 'Isogen',
                'Nocxium', 'Zydrine', 'Megacyte', 'Morphite',
            ])
            ->pluck('t.type_id')->all();

        // Include every material appearing as an input or product in imported
        // PI schematics, covering all imported PI tiers.
        $piIds = array_merge(
            DB::table('eve_planet_schematics')->pluck('product_type_id')->all(),
            DB::table('eve_planet_schematic_inputs')->pluck('type_id')->all()
        );

        return array_values(array_unique(array_filter(array_map('intval', array_merge(
            $compressedOreIds,
            $compressedIceIds,
            $iceProductIds,
            $mineralIds,
            $piIds
        )), fn (int $id) => $id > 0)));
    }

    private function refreshTypeIds(array $typeIds, string $label, JitaMarketPriceRefresher $refresher): int
    {
        $typeIds = array_values(array_unique(array_filter(array_map('intval', $typeIds), fn (int $id) => $id > 0)));
        if (!$typeIds) {
            $this->error('No item types found for ' . $label . '. Check the imported EVE SDE and PI schematics.');
            return self::FAILURE;
        }
        $result = $refresher->refreshTypes($typeIds);
        $this->info($label . ': saved prices for ' . $result['saved'] . ' of ' . count($typeIds) . ' types; ' . $result['failed'] . ' failed. Order-book history was skipped.');
        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
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
        if ($records) app(MarketPriceSnapshotWriter::class)->store($records);
    }
}
