<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OreValueController extends Controller
{
    public function index(Request $request): View
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
            ->whereNotNull('buy_price')
            ->where('buy_price', '>', 0)
            ->groupBy('type_id');

        // Mission/NPE-only ore variants are excluded from the normal asteroid basket.
        $missionOres = [
            'Arisite', 'Augumene', 'Banidine', 'Flawed Gneiss', 'Geodite',
            'Green Arisite', 'Lyavite', 'Mercium', 'Oeryl', 'Pithix', 'Polygypsum', 'Zuthrine',
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

        // CCP SDE category 43 contains the marketable Planetary Commodities (P1-P4).
        $piRecords = DB::table('eve_item_types as item')
            ->join('eve_item_groups as groups', 'groups.group_id', '=', 'item.group_id')
            ->leftJoinSub($latestPrices, 'item_latest', fn ($join) => $join->on('item_latest.type_id', '=', 'item.type_id'))
            ->leftJoin('market_price_snapshots as item_price', 'item_price.id', '=', 'item_latest.latest_id')
            ->where('groups.category_id', 43)
            ->where('item.published', true)
            ->orderBy('groups.group_id')
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

        $piTaxSettings = DB::table('pi_tax_settings')->where('id', 1)->first();
        $importTaxPercent = (float) ($piTaxSettings->import_tax_percent ?? 0);
        $exportTaxPercent = (float) ($piTaxSettings->export_tax_percent ?? 0);

        // PI group IDs from the CCP SDE: P1 Basic, P2 Refined, P3 Specialized, P4 Advanced.
        $tierGroups = [
            'P2' => 1034,
            'P3' => 1040,
            'P4' => 1041,
        ];
        // PI customs taxable values per unit, in ISK, from the in-game tier schedule.
        $piTaxableValues = [
            1042 => 400.0,       // P1
            1034 => 7200.0,      // P2
            1040 => 60000.0,     // P3
            1041 => 1200000.0,   // P4
        ];

        $schematics = DB::table('eve_planet_schematics as schematic')
            ->join('eve_item_types as product', 'product.type_id', '=', 'schematic.product_type_id')
            ->join('eve_item_groups as product_group', 'product_group.group_id', '=', 'product.group_id')
            ->leftJoinSub($latestPrices, 'product_latest', fn ($join) => $join->on('product_latest.type_id', '=', 'product.type_id'))
            ->leftJoin('market_price_snapshots as product_price', 'product_price.id', '=', 'product_latest.latest_id')
            ->where('product_group.category_id', 43)
            ->whereIn('product_group.group_id', array_values($tierGroups))
            ->where('product.published', true)
            ->orderBy('product.type_name')
            ->get([
                'schematic.schematic_id',
                'schematic.name as schematic_name',
                'schematic.cycle_time',
                'schematic.product_type_id',
                'schematic.product_quantity',
                'product.type_name as product_name',
                'product_group.group_id as tier_group_id',
                'product_price.buy_price as output_unit_price',
            ]);

        $schematicIds = $schematics->pluck('schematic_id')->all();
        $schematicInputs = $schematicIds
            ? DB::table('eve_planet_schematic_inputs as input')
                ->join('eve_item_types as input_type', 'input_type.type_id', '=', 'input.type_id')
                ->leftJoin('eve_item_groups as input_group', 'input_group.group_id', '=', 'input_type.group_id')
                ->leftJoinSub($latestPrices, 'input_latest', fn ($join) => $join->on('input_latest.type_id', '=', 'input.type_id'))
                ->leftJoin('market_price_snapshots as input_price', 'input_price.id', '=', 'input_latest.latest_id')
                ->whereIn('input.schematic_id', $schematicIds)
                ->orderBy('input_type.type_name')
                ->get([
                    'input.schematic_id',
                    'input.type_id',
                    'input.quantity',
                    'input_type.type_name',
                    'input_group.category_id as input_category_id',
                    'input_group.group_id as input_tier_group_id',
                    'input_price.buy_price as unit_buy_price',
                ])
                ->groupBy('schematic_id')
            : collect();

        $piProfitability = ['P2' => [], 'P3' => [], 'P4' => []];
        foreach ($schematics as $schematic) {
            $tier = array_search((int) $schematic->tier_group_id, $tierGroups, true);
            if ($tier === false) {
                continue;
            }

            $inputRows = collect($schematicInputs->get($schematic->schematic_id, []));
            $inputs = [];
            $inputsCost = 0.0;
            $importTaxValue = 0.0;
            $completePrices = $inputRows->isNotEmpty() && $schematic->output_unit_price !== null;
            $outputTaxableValue = $piTaxableValues[(int) $schematic->tier_group_id] ?? null;
            if ($outputTaxableValue === null) {
                $completePrices = false;
            }
            foreach ($inputRows as $input) {
                $unitPrice = $input->unit_buy_price !== null ? (float) $input->unit_buy_price : null;
                $lineCost = $unitPrice !== null ? round($unitPrice * (int) $input->quantity, 2) : null;
                $inputTaxableValue = (int) $input->input_category_id === 43
                    ? ($piTaxableValues[(int) $input->input_tier_group_id] ?? null)
                    : null;
                if ($lineCost === null || $inputTaxableValue === null) {
                    $completePrices = false;
                } else {
                    $inputsCost += $lineCost;
                    // PI customs import fees are half the export taxable value times the configured rate.
                    $importTaxValue += $inputTaxableValue * (int) $input->quantity * $importTaxPercent / 100 * 0.5;
                }
                $inputs[] = [
                    'name' => $input->type_name,
                    'quantity' => (int) $input->quantity,
                    'unit_price' => $unitPrice,
                    'line_cost' => $lineCost,
                ];
            }

            $outputUnitPrice = $schematic->output_unit_price !== null ? (float) $schematic->output_unit_price : null;
            $outputRevenue = $outputUnitPrice !== null
                ? round($outputUnitPrice * (int) $schematic->product_quantity, 2)
                : null;
            $inputsCost = round($inputsCost, 2);
            $importTax = $completePrices ? round($importTaxValue, 2) : null;
            $exportTax = $completePrices && $outputRevenue !== null
                ? round($outputTaxableValue * (int) $schematic->product_quantity * $exportTaxPercent / 100, 2)
                : null;
            $landedInputCost = $completePrices ? round($inputsCost + $importTax, 2) : null;
            $netProfit = $completePrices && $outputRevenue !== null
                ? round($outputRevenue - $inputsCost - $importTax - $exportTax, 2)
                : null;
            $profitPercent = $netProfit !== null && $landedInputCost > 0
                ? round($netProfit / $landedInputCost * 100, 2)
                : null;

            $piProfitability[$tier][] = [
                'schematic_id' => (int) $schematic->schematic_id,
                'name' => $schematic->product_name,
                'quantity' => (int) $schematic->product_quantity,
                'cycle_time' => (int) $schematic->cycle_time,
                'inputs' => $inputs,
                'inputs_cost' => $inputsCost,
                'output_unit_price' => $outputUnitPrice,
                'output_revenue' => $outputRevenue,
                'import_tax' => $importTax,
                'export_tax' => $exportTax,
                'net_profit' => $netProfit,
                'profit_percent' => $profitPercent,
                'complete_prices' => $completePrices,
            ];
        }

        foreach ($piProfitability as $tier => $tierRows) {
            $maxAbsPercent = collect($tierRows)
                ->pluck('profit_percent')
                ->filter(fn ($value) => $value !== null)
                ->map(fn ($value) => abs((float) $value))
                ->max() ?: 0;
            foreach ($piProfitability[$tier] as &$row) {
                $row['chart_width'] = $row['profit_percent'] !== null && $maxAbsPercent > 0
                    ? min(50, abs($row['profit_percent']) / $maxAbsPercent * 50)
                    : 0;
                $row['is_profit'] = $row['profit_percent'] !== null && $row['profit_percent'] >= 0;
            }
            unset($row);
            $piProfitability[$tier] = collect($piProfitability[$tier])
                ->sortByDesc(fn ($row) => $row['profit_percent'] ?? -INF)
                ->values()
                ->all();
        }

        $activePiTier = strtoupper((string) $request->query('tier', 'P2'));
        if (!in_array($activePiTier, ['P2', 'P3', 'P4'], true)) {
            $activePiTier = 'P2';
        }

        return view('ore-values.index', compact(
            'rows', 'quantity', 'yieldPercent', 'taxPercent', 'pricedCount', 'latestObservedAt',
            'piItems', 'piQuantity', 'piPricedCount', 'piBasketValue', 'oreSaleValueTotal',
            'combinedSaleValueTotal', 'piTaxSettings', 'importTaxPercent', 'exportTaxPercent',
            'piProfitability', 'activePiTier'
        ));
    }

    public function updateTaxes(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'import_tax_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'export_tax_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'tier' => ['nullable', 'in:P2,P3,P4'],
        ]);

        $settings = [
            'import_tax_percent' => $validated['import_tax_percent'],
            'export_tax_percent' => $validated['export_tax_percent'],
            'updated_at' => now(),
        ];
        if (DB::table('pi_tax_settings')->where('id', 1)->exists()) {
            DB::table('pi_tax_settings')->where('id', 1)->update($settings);
        } else {
            DB::table('pi_tax_settings')->insert($settings + ['id' => 1, 'created_at' => now()]);
        }

        $tier = $validated['tier'] ?? 'P2';
        return redirect(route('ore-values.index') . '?tier=' . urlencode($tier))
            ->with('status', 'PI import and export tax settings saved.');
    }
}
