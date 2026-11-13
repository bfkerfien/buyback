<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OreValueController extends Controller
{
    public function index(): View
    {
        $yieldPercent = 90.628;
        $taxPercent = 0.01;
        $regionId = (string) config('buyback.region_id', '10000002');

        $latestPrices = DB::table('market_price_snapshots')
            ->selectRaw('type_id, MAX(id) as latest_id')
            ->where('region_id', $regionId)
            ->groupBy('type_id');

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
            ->where(function ($query) {
                $query->where('ore.type_name', 'like', 'Compressed %')
                    ->orWhere('ore.type_name', 'like', 'Batch Compressed %');
            })
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
                $ores[$id] = [
                    'type_id' => $id,
                    'name' => $record->ore_name,
                    'portion_size' => max(1, (int) $record->portion_size),
                    'ore_buy_price' => $record->ore_buy_price !== null ? (float) $record->ore_buy_price : null,
                    'ore_market_value' => $record->ore_buy_price !== null ? (float) $record->ore_buy_price * max(1, (int) $record->portion_size) : null,
                    'ore_price_at' => $record->ore_price_at,
                    'materials' => [],
                    'refined_gross' => 0.0,
                    'refined_net' => 0.0,
                    'has_complete_mineral_prices' => true,
                ];
            }

            if ($record->material_type_id === null) {
                $ores[$id]['has_complete_mineral_prices'] = false;
                continue;
            }

            $yieldedQuantity = (int) floor((int) $record->material_quantity * $yieldPercent / 100);
            $mineralPrice = $record->mineral_buy_price !== null ? (float) $record->mineral_buy_price : null;
            $lineValue = $mineralPrice !== null ? $yieldedQuantity * $mineralPrice : null;

            $ores[$id]['materials'][] = [
                'name' => $record->mineral_name ?? ('Type ' . $record->material_type_id),
                'base_quantity' => (int) $record->material_quantity,
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
            $ore['refined_gross'] = round($ore['refined_gross'], 2);
            $ore['refined_tax'] = round($ore['refined_gross'] * $taxPercent / 100, 2);
            $ore['refined_net'] = round($ore['refined_gross'] - $ore['refined_tax'], 2);
            $ore['ore_buy_price'] = $ore['ore_buy_price'] !== null ? round($ore['ore_buy_price'], 2) : null;
            $ore['ore_market_value'] = $ore['ore_market_value'] !== null ? round($ore['ore_market_value'], 2) : null;
            $ore['difference'] = ($ore['ore_market_value'] !== null && $ore['has_complete_mineral_prices'])
                ? round($ore['refined_net'] - $ore['ore_market_value'], 2)
                : null;
            $ore['winner'] = $ore['difference'] === null ? 'insufficient data'
                : ($ore['difference'] > 0 ? 'reprocess' : ($ore['difference'] < 0 ? 'sell compressed' : 'tie'));
            $ore['advantage_percent'] = ($ore['ore_market_value'] !== null && $ore['ore_market_value'] > 0 && $ore['difference'] !== null)
                ? round(abs($ore['difference']) / max($ore['ore_market_value'], $ore['refined_net']) * 100, 2)
                : null;
        }
        unset($ore);

        $rows = collect(array_values($ores))->sortBy('name')->values();
        $pricedCount = $rows->filter(fn ($row) => $row['difference'] !== null)->count();
        $sellCompressedCount = $rows->where('winner', 'sell compressed')->count();
        $reprocessCount = $rows->where('winner', 'reprocess')->count();
        $latestObservedAt = $rows->pluck('ore_price_at')->filter()->max();

        return view('ore-values.index', compact(
            'rows', 'yieldPercent', 'taxPercent', 'pricedCount',
            'sellCompressedCount', 'reprocessCount', 'latestObservedAt'
        ));
    }
}
