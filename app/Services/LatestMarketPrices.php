<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class LatestMarketPrices
{
    /**
     * Return the newest usable buy and sell prices for each requested type.
     * The request is served from the compact current table, never from the
     * unbounded historical snapshot table.
     *
     * @param array<int, int|string> $typeIds
     * @return Collection<int, object>
     */
    public function forTypes(array $typeIds, string $regionId, ?string $source = null): Collection
    {
        $typeIds = array_values(array_unique(array_filter(array_map('intval', $typeIds), fn (int $id) => $id > 0)));
        if (!$typeIds) {
            return collect();
        }

        $rows = DB::table('market_price_current')
            ->where('region_id', $regionId)
            ->whereIn('type_id', $typeIds)
            ->when($source !== null, fn ($query) => $query->where('source', $source))
            ->get([
                'type_id', 'source', 'buy_price', 'sell_price',
                'buy_observed_at', 'sell_observed_at', 'buy_snapshot_id', 'sell_snapshot_id',
            ]);

        return $this->mergeSides($rows);
    }

    /** @param Collection<int, object> $rows */
    private function mergeSides(Collection $rows): Collection
    {
        $prices = [];
        foreach ($rows as $row) {
            $typeId = (int) $row->type_id;
            $prices[$typeId] ??= (object) [
                'type_id' => $typeId,
                'buy_price' => null,
                'sell_price' => null,
                'observed_at' => null,
                'buy_snapshot_id' => 0,
                'sell_snapshot_id' => 0,
            ];
            $price = $prices[$typeId];

            if ($row->buy_snapshot_id !== null && (int) $row->buy_snapshot_id > $price->buy_snapshot_id) {
                $price->buy_price = $row->buy_price !== null ? (float) $row->buy_price : null;
                $price->buy_observed_at = $row->buy_observed_at;
                $price->buy_snapshot_id = (int) $row->buy_snapshot_id;
            }
            if ($row->sell_snapshot_id !== null && (int) $row->sell_snapshot_id > $price->sell_snapshot_id) {
                $price->sell_price = $row->sell_price !== null ? (float) $row->sell_price : null;
                $price->sell_observed_at = $row->sell_observed_at;
                $price->sell_snapshot_id = (int) $row->sell_snapshot_id;
            }

            $buyId = $price->buy_snapshot_id;
            $sellId = $price->sell_snapshot_id;
            $price->observed_at = $buyId >= $sellId ? ($price->buy_observed_at ?? null) : ($price->sell_observed_at ?? null);
        }

        return collect($prices);
    }
}
