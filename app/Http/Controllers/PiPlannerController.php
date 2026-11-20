<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use App\Services\LatestMarketPrices;

class PiPlannerController extends Controller
{
    public function index(LatestMarketPrices $latestMarketPrices): View
    {
        $piTypeIds = DB::table('eve_planet_schematics')->select('product_type_id as type_id')
            ->union(DB::table('eve_planet_schematic_inputs')->select('type_id'))
            ->pluck('type_id')->all();
        $prices = $latestMarketPrices->forTypes($piTypeIds, (string) config('buyback.region_id', '10000002'));

        $taxSettings = DB::table('pi_tax_settings')->where('id', 1)->first();
        $importTaxPercent = (float) ($taxSettings->import_tax_percent ?? 0);
        $exportTaxPercent = (float) ($taxSettings->export_tax_percent ?? 0);
        $targetProfitPercent = (float) ($taxSettings->target_profit_percent ?? 18);
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
                'output.type_name as product_name', 'output.volume as output_volume', 'output_group.group_name as product_group', 'output_group.group_id as output_group_id',
                'i.type_id as input_type_id', 'i.quantity as input_quantity',
                'input.type_name as input_name', 'input_group.group_id as input_group_id'
            )
            ->orderBy('output.type_name')
            ->get()
            ->groupBy('schematic_id')
            ->map(function ($rows) use ($prices, $importTaxPercent, $exportTaxPercent, $targetProfitPercent, $piTaxableValues) {
                $first = $rows->first();
                $outputPrice = $prices->get($first->product_type_id);
                $inputs = $rows->filter(fn ($r) => $r->input_type_id !== null)->map(function ($r) use ($prices) {
                    $price = $prices->get($r->input_type_id);
                    $unitBuyPrice = $price?->buy_price !== null ? (float) $price->buy_price : null;
                    return [
                        'id' => (int) $r->input_type_id,
                        'group_id' => (int) $r->input_group_id,
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
                $transportVolume = $first->output_volume !== null
                    ? (float) $first->output_volume * (int) $first->product_quantity
                    : null;
                $transportFee = $transportVolume !== null ? round($transportVolume * 320, 2) : null;
                $totalProductionCost = $inputCost !== null && $inputTax !== null
                    ? round($inputCost + $inputTax, 2)
                    : null;
                $netSaleValue = $outputBuyValue !== null && $exportTax !== null && $transportFee !== null
                    ? round($outputBuyValue - $exportTax - $transportFee, 2)
                    : null;
                // Target material budget leaves the configured profit margin after
                // import tax, export tax, and outbound transport are all paid.
                $targetMaterialBudget = $outputBuyValue !== null && $inputTax !== null && $exportTax !== null && $transportFee !== null
                    ? round(($outputBuyValue * (1 - ($targetProfitPercent / 100))) - $inputTax - $exportTax - $transportFee, 2)
                    : null;
                $targetBuyPercent = null;
                $targetInputsComplete = $inputCost !== null && $inputs->isNotEmpty()
                    && $inputs->every(fn ($input) => $input['buy_price'] !== null && $input['buy_price'] > 0);
                if ($targetInputsComplete && $targetMaterialBudget !== null && $inputCost > 0 && $targetMaterialBudget >= 0) {
                    $targetBuyPercent = round(($targetMaterialBudget / $inputCost) * 100, 2);
                    $inputs = $inputs->map(function ($input) use ($targetBuyPercent) {
                        $input['target_buy_percent'] = $targetBuyPercent;
                        return $input;
                    });
                }
                $targetProfitValue = $outputBuyValue !== null
                    ? round($outputBuyValue * $targetProfitPercent / 100, 2)
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
                    'output_volume' => $transportVolume,
                    'transport_fee' => $transportFee,
                    'output_price_observed_at' => $outputPrice?->observed_at,
                    'inputs' => $inputs,
                    'input_cost' => $inputCost,
                    'import_tax' => $inputTax,
                    'export_tax' => $exportTax,
                    'total_production_cost' => $totalProductionCost,
                    'net_sale_value' => $netSaleValue,
                    'target_material_budget' => $targetMaterialBudget,
                    'target_buy_percent' => $targetBuyPercent,
                    'target_profit_percent' => $targetProfitPercent,
                    'target_profit_value' => $targetProfitValue,
                    'profit_loss' => $targetMaterialBudget !== null && $targetMaterialBudget >= 0 ? $targetProfitValue : null,
                ];
            })->values();

        // Build one individual appraisal target list for the input tier of each stage.
        $stageTargetConfig = [
            'p1-p2' => ['input_group_id' => 1042, 'input_group_name' => 'P1'],
            'p2-p3' => ['input_group_id' => 1034, 'input_group_name' => 'P2'],
            'p3-p4' => ['input_group_id' => 1040, 'input_group_name' => 'P3'],
        ];
        $savedPiRules = DB::table('appraisal_pi_buy_rules')->get()->keyBy('type_id');
        $tierBuyTargets = [];

        foreach ($stageTargetConfig as $stageKey => $config) {
            $groupId = $config['input_group_id'];
            $targets = [];

            foreach ($recipes->filter(fn ($recipe) => in_array($recipe['output_group'], [
                $stageKey === 'p1-p2' ? 'Refined Commodities - Tier 2' : '',
                $stageKey === 'p2-p3' ? 'Specialized Commodities - Tier 3' : '',
                $stageKey === 'p3-p4' ? 'Advanced Commodities - Tier 4' : '',
            ], true)) as $recipe) {
                foreach ($recipe['inputs'] as $input) {
                    if ((int) ($input['group_id'] ?? 0) !== $groupId) {
                        continue;
                    }
                    $id = (int) $input['id'];
                    if (!isset($targets[$id])) {
                        $targets[$id] = [
                            'type_id' => $id, 'name' => $input['name'], 'jita_buy' => $input['buy_price'],
                            'lowest_percent' => null, 'lowest_recipes' => [], 'recipes' => 0, 'target_complete' => true,
                        ];
                    }
                    $targets[$id]['recipes']++;
                    if (!isset($input['target_buy_percent'])) {
                        $targets[$id]['target_complete'] = false;
                    } else {
                        $current = (float) $input['target_buy_percent'];
                        $recipeName = $recipe['name'] ?: $recipe['output_name'];
                        if ($targets[$id]['lowest_percent'] === null || $current < $targets[$id]['lowest_percent']) {
                            $targets[$id]['lowest_percent'] = $current;
                            $targets[$id]['lowest_recipes'] = [$recipeName];
                        } elseif (abs($current - $targets[$id]['lowest_percent']) < 0.0001) {
                            $targets[$id]['lowest_recipes'][] = $recipeName;
                        }
                    }
                }
            }

            $catalog = DB::table('eve_item_types as item')
                ->join('eve_item_groups as groups', 'groups.group_id', '=', 'item.group_id')
                ->where('groups.category_id', 43)
                ->where('groups.group_id', $groupId)
                ->where('item.published', true)
                ->orderBy('item.type_name')
                ->get(['item.type_id', 'item.type_name']);
            foreach ($catalog as $catalogItem) {
                $id = (int) $catalogItem->type_id;
                if (!isset($targets[$id])) {
                    $price = $prices->get($id);
                    $targets[$id] = [
                        'type_id' => $id, 'name' => $catalogItem->type_name,
                        'jita_buy' => $price?->buy_price !== null ? (float) $price->buy_price : null,
                        'lowest_percent' => null, 'lowest_recipes' => [], 'recipes' => 0, 'target_complete' => false,
                    ];
                }
            }

            $tierBuyTargets[$stageKey] = collect(array_values($targets))
                ->map(function ($item) use ($savedPiRules) {
                    $rule = $savedPiRules->get($item['type_id']);
                    $item['saved_percent'] = $rule ? (float) $rule->buy_percent : null;
                    $item['is_set'] = $rule !== null;
                    $item['can_set'] = $item['target_complete'] && $item['lowest_percent'] !== null;
                    return $item;
                })
                ->sortBy('name')->values();
        }

        return view('pi-planner', compact('recipes', 'importTaxPercent', 'exportTaxPercent', 'targetProfitPercent', 'tierBuyTargets'));
    }

    public function setP1BuyRule(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'type_id' => ['required', 'integer', 'min:1'],
            'buy_percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
        ]);

        $item = DB::table('eve_item_types as item')
            ->join('eve_item_groups as groups', 'groups.group_id', '=', 'item.group_id')
            ->where('item.type_id', $data['type_id'])
            ->where('item.published', true)
            ->where('groups.category_id', 43)
            ->whereIn('groups.group_id', [1042, 1034, 1040])
            ->first(['item.type_id', 'item.type_name']);
        abort_unless($item, 404);

        DB::table('appraisal_pi_buy_rules')->updateOrInsert(
            ['type_id' => (int) $item->type_id],
            [
                'type_name' => $item->type_name,
                'buy_percent' => round((float) $data['buy_percent'], 2),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return redirect()->route('pi-planner.index')->with('status', $item->type_name . ' appraisal buy price set to ' . number_format((float) $data['buy_percent'], 2) . '% of Jita buy.');
    }
}
