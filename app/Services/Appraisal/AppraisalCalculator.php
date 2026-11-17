<?php

namespace App\Services\Appraisal;

use App\Services\Eve\ExcludedMissionOre;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AppraisalCalculator
{
    public function calculate(array $parsedItems, ?string $requiredCategoryName = null, ?float $fixedPayoutPercent = null, bool $ignoreNonMatching = false): array
    {
        // Mission-only ores and special variants are never accepted for buyback.
        $parsedItems = array_values(array_filter(
            $parsedItems,
            fn ($item) => !ExcludedMissionOre::isExcluded($item['name'])
        ));
        if (!$parsedItems) {
            throw ValidationException::withMessages([
                'inventory' => 'Mission-only ores and excluded special ore variants are not accepted for buyback.',
            ]);
        }

        $names = array_column($parsedItems, 'name');
        $types = DB::table('eve_item_types as t')
            ->leftJoin('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->where('t.published', true)->whereIn('t.type_name', $names)
            ->get(['t.type_id','t.type_name','t.group_id','t.portion_size','g.category_id','g.category_name'])
            ->keyBy(fn ($type) => mb_strtolower($type->type_name));

        // Compressed ore is always valued from the mineral output of its
        // corresponding uncompressed ore, never from the compressed ore market price.
        // Keep the compressed type's SDE portion_size (for example, 100 units
        // per refining batch) but use the base ore's mineral recipe and yields.
        $compressedBaseNames = $types->filter(fn ($type) =>
            str_starts_with(mb_strtolower($type->type_name), 'compressed ')
        )->map(fn ($type) => trim(mb_substr($type->type_name, mb_strlen('Compressed '))))->unique()->values();
        $baseOreTypes = $compressedBaseNames->isEmpty() ? collect() : DB::table('eve_item_types as t')
            ->leftJoin('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->where('t.published', true)
            ->whereIn('t.type_name', $compressedBaseNames)
            ->get(['t.type_id', 't.type_name', 't.portion_size', 'g.category_id', 'g.category_name'])
            ->keyBy(fn ($type) => mb_strtolower($type->type_name));
        $oreRecipeTypeIds = $types->mapWithKeys(function ($type) use ($baseOreTypes) {
            $baseName = str_starts_with(mb_strtolower($type->type_name), 'compressed ')
                ? trim(mb_substr($type->type_name, mb_strlen('Compressed ')))
                : null;
            $baseType = $baseName !== null ? $baseOreTypes->get(mb_strtolower($baseName)) : null;
            return [(int) $type->type_id => (int) ($baseType?->type_id ?? $type->type_id)];
        });
        $oreMaterials = DB::table('eve_type_materials')
            ->whereIn('type_id', $oreRecipeTypeIds->values()->unique())
            ->get(['type_id', 'material_type_id', 'quantity'])
            ->groupBy('type_id');
        $mineralIds = $oreMaterials->flatten(1)->pluck('material_type_id')->unique()->values();
        $mineralTypes = $mineralIds->isEmpty() ? collect() : DB::table('eve_item_types')
            ->whereIn('type_id', $mineralIds)->where('published', true)
            ->get(['type_id', 'type_name', 'volume'])->keyBy('type_id');

        $rules = DB::table('appraisal_payout_rules')->get()->keyBy('category_id');
        $piBuyRules = DB::table('appraisal_pi_buy_rules')->get()->keyBy('type_id');
        $mineralPricingSettings = DB::table('buyback_system_status')->where('status_key', 'like', 'mineral_buy_%')->pluck('status_value', 'status_key');
        $unresolvedTypes = [];
        $unpricedTypes = [];
        $lines = [];
        $side = config('buyback.price_side', 'buy');
        $defaultRate = (float) config('buyback.payout_percent', 87);
        $regionId = (string) config('buyback.region_id', '10000002');

        foreach ($parsedItems as $item) {
            $type = $types->get(mb_strtolower($item['name']));
            if (!$type) {
                if (!$ignoreNonMatching) $unresolvedTypes[] = $item['name'];
                continue;
            }

            if ($requiredCategoryName !== null && mb_strtolower((string) $type->category_name) !== mb_strtolower($requiredCategoryName)) {
                if (!$ignoreNonMatching) $unresolved[] = $item['name'] . ' (not in the ' . $requiredCategoryName . ' category)';
                continue;
            }

            $pricingType = $type;
            $pricingQuantity = (int) $item['quantity'];
            $valuationMode = 'market';
            $outputQuantity = null;
            $conversionRatio = null;
            $refinedLineValue = null;
            $refinedMineralDetails = [];

            // Every asteroid ore is valued from refined mineral outputs at the
            // max-refine null-sec Tatara / T2 rig / RX-804 yield (90.628%).
            if ((int) $type->category_id === 25) {
                $recipeTypeId = (int) $oreRecipeTypeIds->get((int) $type->type_id, (int) $type->type_id);
                $recipe = $oreMaterials->get($recipeTypeId, collect());
                $portionSize = max(1, (int) ($type->portion_size ?? 1));
                $refiningInputQuantity = $pricingQuantity;
                $batches = intdiv($refiningInputQuantity, $portionSize);
                if ($recipe->isEmpty() || $batches < 1) {
                    $unresolvedTypes[] = $item['name'] . ' (missing SDE reprocessing recipe or incomplete batch quantity)';
                    continue;
                }

                $refinedLineValue = 0.0;
                foreach ($recipe as $material) {
                    $mineral = $mineralTypes->get($material->material_type_id);
                    if (!$mineral) {
                        $unresolvedTypes[] = $item['name'] . ' (missing refined material type ID ' . $material->material_type_id . ')';
                        continue 2;
                    }
                    $yieldedQuantity = (int) floor((int) $material->quantity * $batches * 0.90628);
                    $mineralId = (int) $mineral->type_id;
                    $defaultDepths = [
                        'Tritanium' => 150000000, 'Pyerite' => 30000000, 'Mexallon' => 3000000,
                        'Isogen' => 2000000, 'Nocxium' => 1000000, 'Zydrine' => 500000,
                        'Megacyte' => 500000, 'Morphite' => 45000,
                    ];
                    $depth = max(1, (int) $mineralPricingSettings->get('mineral_buy_depth_' . $mineralId, $defaultDepths[$mineral->type_name] ?? 50000));
                    $buyPercent = (float) $mineralPricingSettings->get('mineral_buy_percent_' . $mineralId, 87);
                    $mineralPrice = $this->jitaBuyPrice($mineralId, $regionId, $depth, false);
                    if ($mineralPrice === null) {
                        $unpricedTypes[] = $mineral->type_name . ' (refined output for ' . $item['name'] . ': insufficient Jita buy volume at configured ' . number_format($depth) . '-unit depth)';
                        continue 2;
                    }
                    $grossMineralValue = round($yieldedQuantity * $mineralPrice['price'], 2);
                    $mineralValue = round($grossMineralValue * $buyPercent / 100, 2);
                    $mineralVolume = (float) ($mineral->volume ?? 0);
                    $shippingVolume = $yieldedQuantity * $mineralVolume;
                    $shippingCost = round($shippingVolume * 320, 2);
                    $refinedLineValue += $mineralValue - $shippingCost;
                    $refinedMineralDetails[] = [
                        'type_id' => $mineralId,
                        'name' => $mineral->type_name,
                        'quantity' => $yieldedQuantity,
                        'unit_price' => $mineralPrice['price'],
                        'buy_percent' => $buyPercent,
                        'price_depth' => $depth,
                        'volume_m3' => $shippingVolume,
                        'shipping_cost' => $shippingCost,
                        'gross_value' => $grossMineralValue,
                        'value' => $mineralValue - $shippingCost,
                    ];
                }
                $refinedLineValue = round($refinedLineValue, 2);
                $outputQuantity = array_sum(array_column($refinedMineralDetails, 'quantity'));
                $valuationMode = 'reprocessed';
                $unitPrice = $refinedLineValue / $pricingQuantity;
                $price = (object) ['source' => 'sde-refined-outputs-50000-depth', 'observed_at' => now()];
                $lineValue = $refinedLineValue;
            } else {
                $priceColumn = $side === 'sell' ? 'sell_price' : 'buy_price';
                if ($side === 'sell') {
                    $price = DB::table('market_price_snapshots')
                        ->where('type_id', $type->type_id)->where('region_id', $regionId)
                        ->whereNotNull($priceColumn)->where($priceColumn, '>', 0)
                        ->orderByDesc('observed_at')->first(['buy_price','sell_price','source','observed_at']);
                    $unitPrice = $price ? (float) $price->{$priceColumn} : null;
                } else {
                    $marketPrice = $this->jitaBuyPrice((int) $type->type_id, $regionId, 50000);
                    $unitPrice = $marketPrice['price'] ?? null;
                    $price = $marketPrice ? (object) ['source' => 'jita-50000-unit-depth', 'observed_at' => $marketPrice['observed_at']] : null;
                }
                if ($unitPrice === null || $unitPrice <= 0 || !$price) {
                    $unpricedTypes[] = $item['name'] . ' (type ID ' . $type->type_id . ': no valid Jita price)';
                    continue;
                }
                $lineValue = round($unitPrice * $pricingQuantity, 2);
            }

            $effectiveCategoryId = $pricingType->category_id;
            $effectiveCategoryName = $pricingType->category_name;
            $payoutRule = $effectiveCategoryId ? $rules->get($effectiveCategoryId) : null;
            // P1 planetary commodities use their individually configured appraisal
            // buy percentage instead of the shared PI category payout percentage.
            $piBuyRule = ((int) $type->category_id === 43 && (int) $type->group_id === 1042)
                ? $piBuyRules->get($type->type_id)
                : null;
            // Mercoxit in every grade and Morphite have a fixed 87% buyback rate.
            $special87PercentNames = [
                'mercoxit',
                'magma mercoxit',
                'vitreous mercoxit',
                'compressed mercoxit',
                'compressed magma mercoxit',
                'compressed vitreous mercoxit',
                'morphite',
            ];
            $hasSpecial87Percent = in_array(mb_strtolower($type->type_name), $special87PercentNames, true);
            $rate = (int) $type->category_id === 25
                ? 100.0
                : ($fixedPayoutPercent !== null
                    ? $fixedPayoutPercent
                    : ($hasSpecial87Percent ? 87.0 : (float) ($piBuyRule->buy_percent ?? $payoutRule->payout_percent ?? $defaultRate)));
            // Normalize every existing 90% payout rule to 87%, including
            // category rules, individual PI rules, and explicitly fixed rates.
            if (abs($rate - 90.0) < 0.000001) {
                $rate = 87.0;
            }

            $lines[] = [
                'type_id' => (int) $type->type_id,
                'type_name' => $type->type_name,
                'quantity' => (int) $item['quantity'],
                // Equivalent value per submitted unit, including reprocessing conversions.
                'unit_price' => round($lineValue / (int) $item['quantity'], 4),
                'line_value' => $lineValue,
                'payout_percent' => $rate,
                'payout_value' => round($lineValue * $rate / 100, 2),
                'category_id' => $effectiveCategoryId,
                'category_name' => $effectiveCategoryName,
                'rate_source' => $fixedPayoutPercent !== null ? 'fixed' : ($piBuyRule ? 'individual_pi' : ($payoutRule ? 'category' : 'default')),
                'source' => $price->source,
                'observed_at' => $price->observed_at,
                'valuation_mode' => $valuationMode,
                'output_type_id' => $valuationMode === 'reprocessed' ? (int) $pricingType->type_id : null,
                'output_name' => $valuationMode === 'reprocessed' ? $pricingType->type_name : null,
                'output_quantity' => $outputQuantity,
                'conversion_ratio' => $conversionRatio,
                'output_unit_price' => $unitPrice,
                'refined_materials' => $refinedMineralDetails,
            ];
        }

        $priceErrors = [];
        if ($unresolvedTypes) {
            $priceErrors[] = 'These item types are missing from the imported published EVE SDE: ' . implode(', ', array_unique($unresolvedTypes)) . '. Re-import CCP types.jsonl and taxonomy.';
        }
        if ($unpricedTypes) {
            $priceErrors[] = 'These item types exist in the SDE but have no stored positive Jita ' . $side . ' price: ' . implode(', ', array_unique($unpricedTypes)) . '. Refresh their Jita prices before appraising.';
        }
        if ($priceErrors) {
            throw ValidationException::withMessages(['inventory' => $priceErrors]);
        }

        if (!$lines) {
            $message = $ignoreNonMatching
                ? 'No priced ship hulls were found in that paste. Non-ship entries are ignored; paste one or more published ship hull names and quantities.'
                : 'No recognized items were found to appraise.';
            throw ValidationException::withMessages(['inventory' => [$message]]);
        }

        $gross = round(array_sum(array_column($lines, 'line_value')), 2);
        $payout = round(array_sum(array_column($lines, 'payout_value')), 2);

        return [
            'lines' => $lines,
            'gross_value' => $gross,
            'fees' => round($gross - $payout, 2),
            'payout_value' => $payout,
            'price_side' => $side,
            'payout_percent' => $fixedPayoutPercent !== null ? $fixedPayoutPercent : $defaultRate,
            'region_id' => $regionId,
        ];
    }

    private function jitaBuyPrice(int $typeId, string $regionId, int $requiredDepth, bool $checkAverage = true): ?array
    {
        $latestBookAt = DB::table('market_order_book_snapshots')
            ->where('type_id', $typeId)->where('region_id', $regionId)
            ->where('location_id', (int) config('buyback.jita_station_id', 60003760))
            ->where('is_buy_order', true)->max('observed_at');
        if (!$latestBookAt) return null;

        $orders = DB::table('market_order_book_snapshots')
            ->where('type_id', $typeId)->where('region_id', $regionId)
            ->where('location_id', (int) config('buyback.jita_station_id', 60003760))
            ->where('is_buy_order', true)->where('observed_at', $latestBookAt)
            ->orderByDesc('price')->get(['price', 'volume_remain']);
        $remaining = $requiredDepth;
        $price = null;
        foreach ($orders as $order) {
            if ((int) $order->volume_remain >= $remaining) { $price = (float) $order->price; break; }
            $remaining -= (int) $order->volume_remain;
        }
        if ($price === null || $price <= 0) return null;

        if ($checkAverage) {
            $average = (float) DB::table('market_price_snapshots')
                ->where('type_id', $typeId)->where('region_id', $regionId)
                ->where('source', 'esi-jita-4-4')->where('observed_at', '>=', now()->subDays(7))
                ->whereNotNull('buy_price')->where('buy_price', '>', 0)->avg('buy_price');
            if ($average <= 0 || abs($price - $average) / $average > 0.03) return null;
        }

        return ['price' => $price, 'observed_at' => $latestBookAt];
    }
}
