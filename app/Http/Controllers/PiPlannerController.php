<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PiPlannerController extends Controller
{
    public function index(): View
    {
        $latestPriceIds = DB::table('market_price_snapshots')
            ->select('type_id', DB::raw('MAX(id) as latest_id'))
            ->where('region_id', (string) config('buyback.region_id', '10000002'))
            ->groupBy('type_id');

        $prices = DB::table('market_price_snapshots as price')
            ->joinSub($latestPriceIds, 'latest', function ($join) {
                $join->on('latest.latest_id', '=', 'price.id');
            })
            ->get(['price.type_id', 'price.buy_price', 'price.sell_price', 'price.observed_at'])
            ->keyBy('type_id');

        $taxSettings = DB::table('pi_tax_settings')->where('id', 1)->first();
        $importTaxPercent = (float) ($taxSettings->import_tax_percent ?? 0);
        $exportTaxPercent = (float) ($taxSettings->export_tax_percent ?? 0);
        $piTaxableValues = [
            1042 => 400.0,
            1034 => 7200.0,
            1040 => 60000.0,
            1041 => 1200000.0,
        ];

        $recipes = DB::table('eve_planet_schematics as s')
            ->join('eve_item_types as output', 'output.type_id', '=', 's.product_type_id')
            ->join('eve_item_groups as output_group', 'output_group.group_id', '=', 'output.group_id')
            ->leftJoin('eve_planet_schematic_inputs as i', 'i.schematic_id', '=', 's.schematic_id')
            ->leftJoin('eve_item_types as input', 'input.type_id', '=', 'i.type_id')
            ->leftJoin('eve_item_groups as input_group', 'input_group.group_id', '=', 'input.group_id')
            ->select(
                's.schematic_id', 's.name as schematic_name', 's.cycle_time',
                's.product_type_id', 's.product_quantity',
                'output.type_name as product_name', 'output_group.group_name as product_group', 'output_group.group_id as output_group_id',
                'i.type_id as input_type_id', 'i.quantity as input_quantity',
                'input.type_name as input_name', 'input_group.group_id as input_group_id'
            )
            ->orderBy('output.type_name')
            ->get()
            ->groupBy('schematic_id')
            ->map(function ($rows) use ($prices, $importTaxPercent, $exportTaxPercent, $piTaxableValues) {
                $first = $rows->first();
                $outputPrice = $prices->get($first->product_type_id);
                $inputs = $rows->filter(fn ($r) => $r->input_type_id !== null)->map(function ($r) use ($prices) {
                    $price = $prices->get($r->input_type_id);
                    $unitBuyPrice = $price?->buy_price !== null ? (float) $price->buy_price : null;
                    return [
                        'id' => (int) $r->input_type_id,
                        'name' => $r->input_name ?: ('Type #' . $r->input_type_id),
                        'quantity' => (int) $r->input_quantity,
                        'buy_price' => $unitBuyPrice,
                        'run_cost' => $unitBuyPrice !== null ? $unitBuyPrice * (int) $r->input_quantity : null,
                        'price_observed_at' => $price?->observed_at,
                    ];
                })->values();

                $inputCost = $inputs->contains(fn ($input) => $input['run_cost'] === null)
                    ? null
                    : (float) $inputs->sum('run_cost');
                $inputTax = 0.0;
                $inputTaxComplete = true;
                foreach ($rows->filter(fn ($r) => $r->input_type_id !== null) as $inputRow) {
                    $taxableValue = $piTaxableValues[(int) $inputRow->input_group_id] ?? null;
                    if ($taxableValue === null) {
                        $inputTaxComplete = false;
                        break;
                    }
                    $inputTax += $taxableValue * (int) $inputRow->input_quantity * $importTaxPercent / 100 * 0.5;
                }
                $outputTaxableValue = $piTaxableValues[(int) $first->output_group_id] ?? null;
                $exportTax = $outputTaxableValue !== null
                    ? $outputTaxableValue * (int) $first->product_quantity * $exportTaxPercent / 100
                    : null;
                $inputTax = $inputTaxComplete ? round($inputTax, 2) : null;
                $outputUnitBuyPrice = $outputPrice?->buy_price !== null ? (float) $outputPrice->buy_price : null;
                $outputBuyValue = $outputUnitBuyPrice !== null
                    ? $outputUnitBuyPrice * (int) $first->product_quantity
                    : null;
                $totalProductionCost = $inputCost !== null && $inputTax !== null
                    ? round($inputCost + $inputTax, 2)
                    : null;
                $netSaleValue = $outputBuyValue !== null && $exportTax !== null
                    ? round($outputBuyValue - $exportTax, 2)
                    : null;
                // Maximum common percentage of current Jita buy prices payable for every input
                // while keeping the all-in production cost (including both customs fees)
                // at or below 82% of the P2 output's gross Jita buy value.
                $targetMaterialBudget = $outputBuyValue !== null && $inputTax !== null && $exportTax !== null
                    ? round(($outputBuyValue * 0.82) - $inputTax - $exportTax, 2)
                    : null;
                $targetBuyPercent = $targetMaterialBudget !== null && $inputCost !== null && $inputCost > 0
                    ? round(($targetMaterialBudget / $inputCost) * 100, 2)
                    : null;

                return [
                    'id' => (int) $first->schematic_id,
                    'name' => $first->schematic_name,
                    'cycle_time' => (int) $first->cycle_time,
                    'output_id' => (int) $first->product_type_id,
                    'output_name' => $first->product_name,
                    'output_group' => $first->product_group,
                    'output_quantity' => (int) $first->product_quantity,
                    'output_buy_price' => $outputUnitBuyPrice,
                    'output_buy_value' => $outputBuyValue,
                    'output_price_observed_at' => $outputPrice?->observed_at,
                    'inputs' => $inputs,
                    'input_cost' => $inputCost,
                    'import_tax' => $inputTax,
                    'export_tax' => $exportTax,
                    'total_production_cost' => $totalProductionCost,
                    'net_sale_value' => $netSaleValue,
                    'target_material_budget' => $targetMaterialBudget,
                    'target_buy_percent' => $targetBuyPercent,
                    'profit_loss' => $totalProductionCost !== null && $netSaleValue !== null
                        ? round($netSaleValue - $totalProductionCost, 2)
                        : null,
                ];
            })->values();

        return view('pi-planner', compact('recipes', 'importTaxPercent', 'exportTaxPercent'));
    }
}
