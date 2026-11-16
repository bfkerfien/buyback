<?php

namespace App\\Http\\Controllers;

use Illuminate\\Support\\Facades\\DB;
use Illuminate\\View\\View;

class OreValueController extends Controller
{
    public function index(): View
    {
        // Maximum-skill null-sec Tatara with T2 reprocessing rig and RX-804 implant.
        $quantity = 10000;
        $piQuantity = 1000;
        $yieldPercent = 90.628;
        $taxPercent = 0.01;
        $regionId = (string) config('buyback.region_id', '10000002');

        $latestPrices = DB::table('market_price_snapshots')
            ->selectRaw('type_id, MAX(id) as latest_id')
            ->where('region_id', $regionId)
            ->groupBy('type_id');

        // These are the SDE mission/NPE ore variants, rather than normal mineable ore.
        // Exclude the entire group so the comparison stays focused on regular asteroid ore.
        $missionOres = [
            'Augumene', 'Banidine', 'Geodite', 'Green Arisite', 'Lyavite',
            'Mercium', 'Oeryl', 'Pithix', 'Polygypsum', 'Zuthrine',
        ];

        $records = DB::table('eve_item_types as ore')
            ->join('eve_item_groups as groups', 'groups.group_id', '=', 'ore.group_id')
            ->leftJoin('eve_type_materials as materials', 'materials.type_id', '=', 'ore.type_id')
            ->leftJoin('eve_item_types as mineral', 'mineral.type_id', '=', 'materials.material_type_id')
            ->leftJoinSub($latestPrices, 'ore_latest', fn ($join) => $join->on('ore_latest.type_id', '=', 'ore.type_id'))
            ->leftJoin('market_price_snapshots as ore_price', 'ore_price.id', '=', 'ore_latest.latest_id')
            ->leftJoinSub($latestPrices, 'mineral_latest', fn ($join) => $join->on('mineral_latest.type_id', '=', 'materials.material_type_id'))
            ->leftJoin('market_price_snapshots as mineral_price', 'mineral_price.id', '=', 'mineral_latest.latest_id')
            ->where('groups.category_name', 'Asteroid')
            ->where('ore.published', true)
            ->whereNotIn('ore.type_name', $missionOres)
            ->orderBy('ore.type_name')
            ->get([
                'ore.type_id as ore_type_id', 'ore.type_name as ore_name', 'ore.portion_size',
                'ore_price.buy_price as ore_buy_price', 'ore_price.observed_at as ore_price_at',
                'materials.material_type_id', 'materials.quantity as material_quantity',
                'mineral.type_name as mineral_name', 'mineral_price.buy_price as mineral_buy_price',
                'mineral_price.observed_at as mineral_price_at',
            ]);

        $ores = [];
        foreach ($records as $record) {
            $id = (int) $record->ore_type_id;
            if (!isset($ores[$id])) {
                $portionSize = max(1, (int) $record->portion_size);
                $batches = intdiv($quantity, $portionSize);
                $processedQuantity = $batches * $portionSize;
                $unitBuyPrice = $record->ore_buy_price !== null ? (float) $record->ore_buy_price : null;

                $ores[$id] = [
                    'type_id' => $id,
                    'name' => $record->ore_name,
                    'quantity' => $quantity,
                    'portion_size' => $portionSize,
                    'batches' => $batches,
                    'processed_quantity' => $processedQuantity,
                    'leftover_quantity' => $quantity - $processedQuantity,
                    'ore_buy_price' => $unitBuyPrice,
                    'ore_market_value' => $unitBuyPrice !== null ? round($unitBuyPrice * $quantity, 2) : null,
                    'ore_price_at' => $record->ore_price_at,
                    'materials' => [],
                    'refined_gross' => 0.0,
                    'refined_net' => 0.0,
                    'has_complete_mineral_prices' => true,
                    'has_recipe' => false,
                ];
            }

            if ($record->material_type_id === null) {
                continue;
            }

            $ores[$id]['has_recipe'] = true;
            $baseQuantity = (int) $record->material_quantity * $ores[$id]['batches'];
            $yieldedQuantity = (int) floor($baseQuantity * $yieldPercent / 100);
            $mineralPrice = $record->mineral_buy_price !== null ? (float) $record->mineral_buy_price : null;
            $lineValue = $mineralPrice !== null ? round($yieldedQuantity * $mineralPrice, 2) : null;

            $ores[$id]['materials'][] = [
                'name' => $record->mineral_name ?? ('Type ' . $record->material_type_id),
                'base_quantity' => $baseQuantity,
                'yielded_quantity' => $yieldedQuantity,
                'buy_price' => $mineralPrice,
                'value' => $lineValue,
            ];

            if ($lineValue === null) {
                $ores[$id]['has_complete_mineral_prices'] = false;
            } else {
                $ores[$id]['refined_gross'] += $lineValue;
            }
        }

        foreach ($ores as &$ore) {
            if (!$ore['has_recipe'] || $ore['batches'] < 1) {
                $ore['has_complete_mineral_prices'] = false;
            }
            $ore['refined_gross'] = round($ore['refined_gross'], 2);
            $ore['refined_tax'] = round($ore['refined_gross'] * $taxPercent / 100, 2);
            $ore['refined_net'] = round($ore['refined_gross'] - $ore['refined_tax'], 2);
            $ore['ore_buy_price'] = $ore['ore_buy_price'] !== null ? round($ore['ore_buy_price'], 2) : null;
            $ore['difference'] = ($ore['ore_market_value'] !== null && $ore['has_complete_mineral_prices'])
                ? round($ore['refined_net'] - $ore['ore_market_value'], 2)
                : null;
            $ore['winner'] = $ore['difference'] === null ? 'incomplete data'
                : ($ore['difference'] > 0 ? 'refine' : ($ore['difference'] < 0 ? 'sell ore' : 'tie'));
        }
        unset($ore);

        $rows = collect(array_values($ores))->sortBy('name')->values();
        $pricedCount = $rows->filter(fn ($row) => $row['ore_market_value'] !== null && $row['has_complete_mineral_prices'])->count();
        $latestObservedAt = $rows->pluck('ore_price_at')->filter()->max();
        $oreSaleValueTotal = round($rows->sum(fn ($row) => $row['ore_market_value'] ?? 0), 2);

        // Category 43 is Planetary Commodities in the CCP SDE: all marketable PI tiers.
        // Price each commodity as a 1,000-unit lot, using the same regional buy snapshots.
        $piRecords = DB::table('eve_item_types as item')
            ->join('eve_item_groups as groups', 'groups.group_id', '=', 'item.group_id')
            ->leftJoinSub($latestPrices, 'item_latest', fn ($join) => $join->on('item_latest.type_id', '=', 'item.type_id'))
            ->leftJoin('market_price_snapshots as item_price', 'item_price.id', '=', 'item_latest.latest_id')
            ->where('groups.category_id', 43)
            ->where('item.published', true)
            ->orderBy('groups.group_name')
            ->orderBy('item.type_name')
            ->get([
                'item.type_id',
                'item.type_name',
                'groups.group_name',
                'item_price.buy_price',
                'item_price.observed_at',
            ]);

        $piItems = $piRecords->map(function ($record) use ($piQuantity) {
            $unitPrice = $record->buy_price !== null ? (float) $record->buy_price : null;

            return [
                'type_id' => (int) $record->type_id,
                'name' => $record->type_name,
                'tier' => $record->group_name,
                'quantity' => $piQuantity,
                'unit_buy_price' => $unitPrice,
                'market_value' => $unitPrice !== null ? round($unitPrice * $piQuantity, 2) : null,
                'observed_at' => $record->observed_at,
            ];
        })->values();

        $piPricedCount = $piItems->filter(fn ($item) => $item['market_value'] !== null)->count();
        $piBasketValue = round($piItems->sum(fn ($item) => $item['market_value'] ?? 0), 2);
        $combinedSaleValueTotal = round($oreSaleValueTotal + $piBasketValue, 2);

        return view('ore-values.index', compact(
            'rows', 'quantity', 'yieldPercent', 'taxPercent', 'pricedCount', 'latestObservedAt',
            'piItems', 'piQuantity', 'piPricedCount', 'piBasketValue', 'oreSaleValueTotal',
            'combinedSaleValueTotal'
        ));
    }
}
