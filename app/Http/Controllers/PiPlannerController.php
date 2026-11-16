<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                'output.type_name as product_name', 'output.volume as output_volume', 'output_group.group_name as product_group', 'output_group.group_id as output_group_id',
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
                // Maximum common percentage of current Jita buy prices payable for every input
                // while keeping the all-in production cost (including both customs fees)
                // at or below 82% of the P2 output's gross Jita buy value.
                $targetMaterialBudget = $outputBuyValue !== null && $inputTax !== null && $exportTax !== null && $transportFee !== null
                    ? round(($outputBuyValue * 0.82) - $inputTax - $exportTax - $transportFee, 2)
                    : null;
                // Keep every input at or above 80% of Jita buy. Any budget above
                // that floor is weighted toward higher-value inputs, so a more valuable
                // material receives a higher percentage than a cheaper one.
                $targetBuyPercent = null;
                $targetInputsComplete = $inputCost !== null && $inputs->isNotEmpty()
                    && $inputs->every(fn ($input) => $input['buy_price'] !== null && $input['buy_price'] > 0);
                if ($targetInputsComplete && $targetMaterialBudget !== null && $inputCost > 0) {
                    $minimumMaterialBudget = $inputCost * 0.80;
                    if ($targetMaterialBudget < $minimumMaterialBudget) {
                        // The buy-rate floor takes precedence when the production
                        // budget cannot support it: report 80% rather than no target.
                        $inputs = $inputs->map(function ($input) {
                            $input['target_buy_percent'] = 80.0;
                            return $input;
                        });
                        $targetBuyPercent = 80.0;
                    } else {
                        if ($targetMaterialBudget >= $inputCost) {
                            $inputs = $inputs->map(function ($input) {
                                $input['target_buy_percent'] = 100.0;
                                return $input;
                            });
                        } else {
                            // Solve for a common value-weighted uplift: target percentage
                            // = 80% + (unit Jita buy price × factor). Higher-value inputs
                            // therefore receive a higher percentage, while no input falls
                            // below 80%. Quantities are included in the budget calculation.
                            $low = 0.0;
                            $high = 20.0 / min($inputs->pluck('buy_price')->all());
                            for ($iteration = 0; $iteration < 64; $iteration++) {
                                $factor = ($low + $high) / 2;
                                $proposedSpend = $inputs->sum(function ($input) use ($factor) {
                                    $percent = min(100.0, 80.0 + ($input['buy_price'] * $factor));
                                    return $input['run_cost'] * $percent / 100;
                                });
                                if ($proposedSpend > $targetMaterialBudget) {
                                    $high = $factor;
                                } else {
                                    $low = $factor;
                                }
                            }
                            $factor = $low;
                            $inputs = $inputs->map(function ($input) use ($factor) {
                                $input['target_buy_percent'] = round(min(100.0, 80.0 + ($input['buy_price'] * $factor)), 2);
                                return $input;
                            });
                        }
                        $targetBuyPercent = round($inputs->sum(fn ($input) => $input['run_cost'] * $input['target_buy_percent'] / 100) / $inputCost * 100, 2);
                    }
                }

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
                    'profit_loss' => $totalProductionCost !== null && $netSaleValue !== null
                        ? round($netSaleValue - $totalProductionCost, 2)
                        : null,
                ];
            })->values();

        // One row per P1 input, using the lowest recommended percentage across
        // every P1 -> P2 recipe that consumes it. If any consuming recipe has no
        // feasible target, do not offer a misleading aggregate Set action.
        $p1Targets = [];
        foreach ($recipes->filter(fn ($recipe) => strcasecmp($recipe['output_group'], 'Refined Commodities - Tier 2') === 0) as $recipe) {
            foreach ($recipe['inputs'] as $input) {
                $id = (int) $input['id'];
                if (!isset($p1Targets[$id])) {
                    $p1Targets[$id] = [
                        'type_id' => $id,
                        'name' => $input['name'],
                        'jita_buy' => $input['buy_price'],
                        'lowest_percent' => null,
                        'recipes' => 0,
                        'target_complete' => true,
                    ];
                }
                $p1Targets[$id]['recipes']++;
                if (!isset($input['target_buy_percent'])) {
                    $p1Targets[$id]['target_complete'] = false;
                } else {
                    $current = (float) $input['target_buy_percent'];
                    $p1Targets[$id]['lowest_percent'] = $p1Targets[$id]['lowest_percent'] === null
                        ? $current
                        : min($p1Targets[$id]['lowest_percent'], $current);
                }
            }
        }
        // Include every published P1 commodity from the SDE, even if no loaded
        // schematic currently consumes it; those rows remain unset until calculable.
        $p1Catalog = DB::table('eve_item_types as item')
            ->join('eve_item_groups as groups', 'groups.group_id', '=', 'item.group_id')
            ->where('groups.category_id', 43)
            ->where('groups.group_id', 1042)
            ->where('item.published', true)
            ->orderBy('item.type_name')
            ->get(['item.type_id', 'item.type_name']);
        foreach ($p1Catalog as $p1Item) {
            $id = (int) $p1Item->type_id;
            if (!isset($p1Targets[$id])) {
                $price = $prices->get($id);
                $p1Targets[$id] = [
                    'type_id' => $id,
                    'name' => $p1Item->type_name,
                    'jita_buy' => $price?->buy_price !== null ? (float) $price->buy_price : null,
                    'lowest_percent' => null,
                    'recipes' => 0,
                    'target_complete' => false,
                ];
            }
        }

        $savedPiRules = DB::table('appraisal_pi_buy_rules')->get()->keyBy('type_id');
        $p1BuyTargets = collect(array_values($p1Targets))
            ->map(function ($item) use ($savedPiRules) {
                $rule = $savedPiRules->get($item['type_id']);
                $item['saved_percent'] = $rule ? (float) $rule->buy_percent : null;
                $item['is_set'] = $rule !== null;
                $item['can_set'] = $item['target_complete'] && $item['lowest_percent'] !== null;
                return $item;
            })
            ->sortBy('name')
            ->values();

        return view('pi-planner', compact('recipes', 'importTaxPercent', 'exportTaxPercent', 'p1BuyTargets'));
    }

    public function setP1BuyRule(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'type_id' => ['required', 'integer', 'min:1'],
            'buy_percent' => ['required', 'numeric', 'min:80', 'max:100'],
        ]);

        $item = DB::table('eve_item_types as item')
            ->join('eve_item_groups as groups', 'groups.group_id', '=', 'item.group_id')
            ->where('item.type_id', $data['type_id'])
            ->where('item.published', true)
            ->where('groups.category_id', 43)
            ->where('groups.group_id', 1042)
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
