<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class MarketPriceSnapshotWriter
{
    /**
     * Append immutable price history and refresh the compact current-price rows
     * in the same transaction. Null or non-positive prices do not erase the
     * last usable value, matching the existing appraisal lookup behavior.
     *
     * @param array<int, array<string, mixed>> $records
     */
    public function store(array $records): void
    {
        foreach (array_chunk($records, 500) as $chunk) {
            DB::transaction(function () use ($chunk): void {
                DB::table('market_price_snapshots')->insert($chunk);

                $groups = collect($chunk)->groupBy(fn (array $row) => $row['region_id'] . "\0" . $row['source']);
                foreach ($groups as $group) {
                    $first = $group->first();
                    $typeIds = $group->pluck('type_id')->unique()->values()->all();
                    $newestByType = DB::table('market_price_snapshots')
                        ->where('region_id', $first['region_id'])
                        ->where('source', $first['source'])
                        ->where('created_at', $first['created_at'])
                        ->whereIn('type_id', $typeIds)
                        ->orderByDesc('id')
                        ->get(['id', 'type_id', 'region_id', 'source', 'buy_price', 'sell_price', 'observed_at'])
                        ->unique('type_id')
                        ->values();

                    $now = now();
                    $buyRows = $newestByType
                        ->filter(fn ($row) => $row->buy_price !== null && (float) $row->buy_price > 0)
                        ->map(fn ($row) => [
                            'region_id' => $row->region_id,
                            'type_id' => $row->type_id,
                            'source' => $row->source,
                            'buy_price' => $row->buy_price,
                            'buy_observed_at' => $row->observed_at,
                            'buy_snapshot_id' => $row->id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])->all();

                    $sellRows = $newestByType
                        ->filter(fn ($row) => $row->sell_price !== null && (float) $row->sell_price > 0)
                        ->map(fn ($row) => [
                            'region_id' => $row->region_id,
                            'type_id' => $row->type_id,
                            'source' => $row->source,
                            'sell_price' => $row->sell_price,
                            'sell_observed_at' => $row->observed_at,
                            'sell_snapshot_id' => $row->id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])->all();

                    if ($buyRows) {
                        DB::table('market_price_current')->upsert(
                            $buyRows,
                            ['region_id', 'type_id', 'source'],
                            ['buy_price', 'buy_observed_at', 'buy_snapshot_id', 'updated_at']
                        );
                    }
                    if ($sellRows) {
                        DB::table('market_price_current')->upsert(
                            $sellRows,
                            ['region_id', 'type_id', 'source'],
                            ['sell_price', 'sell_observed_at', 'sell_snapshot_id', 'updated_at']
                        );
                    }
                }
            });
        }
    }
}
