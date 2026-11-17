<?php

namespace App\Services\Appraisal;

use App\Services\Eve\ExcludedMissionOre;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

final class AppraisalCalculator
{
    private array $liveOrderBooks = [];

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
            ->get(['t.type_id','t.type_name','t.group_id','t.portion_size','g.category_id','g.category_name','g.group_name'])
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
            ->get(['t.type_id', 't.type_name', 't.portion_size', 'g.category_id', 'g.category_name', 'g.group_name'])
            ->keyBy(fn ($type) => mb_strtolower($type->type_name));
        $oreRecipeTypeIds = $types->mapWithKeys(function ($type) use ($baseOreTypes) {
            $baseName = str_starts_with(mb_strtolower($type->type_name), 'compressed ')
                ? trim(mb_substr($type->type_name, mb_strlen('Compressed ')))
                : null;
            $baseType = $baseName !== null ? $baseOreTypes->get(mb_strtolower($baseName)) : null;
            return [(int) $type->type_id => (int) ($baseType?->type_id ?? (str_starts_with(mb_strtolower($type->type_name), 'compressed ') ? 0 : $type->type_id))];
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
        $icePricingSettings = DB::table('buyback_system_status')->where('status_key', 'like', 'ice_%')->pluck('status_value', 'status_key');
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
            if (str_starts_with(mb_strtolower($type->type_name), 'compressed ')
                && (int) $oreRecipeTypeIds->get((int) $type->type_id, 0) === 0) {
                $unresolvedTypes[] = $item['name'] . ' (matching uncompressed ore recipe is missing from the SDE)';
                continue;
            }
            $valuationMode = 'market';
            $outputQuantity = null;
            $conversionRatio = null;
            $refinedLineValue = null;
            $refinedMineralDetails = [];
            $customPayoutPercent = null;
            $customRateSource = null;
            $isIce = mb_strtolower((string) ($type->group_name ?? '')) === 'ice';
            $baseTypeName = str_starts_with(mb_strtolower($type->type_name), 'compressed ')
                ? trim(mb_substr($type->type_name, mb_strlen('Compressed ')))
                : $type->type_name;
            $isSpecialWhiteGlaze = $isIce && in_array(mb_strtolower($baseTypeName), ['white glaze', 'pristine white glaze'], true);
            $isHeavyWater = mb_strtolower($type->type_name) === 'heavy water';

            // White Glaze and Pristine White Glaze use SDE ice products. Their
            // market-priced outputs have independent rates; Heavy Water uses an
            // admin-entered ISK/unit value (10% of Jita buy until configured).
            if ($isSpecialWhiteGlaze) {
                $recipeTypeId = (int) $oreRecipeTypeIds->get((int) $type->type_id, (int) $type->type_id);
                $recipe = $oreMaterials->get($recipeTypeId, collect());
                $baseType = $baseOreTypes->get(mb_strtolower($baseTypeName));
                $portionSize = max(1, (int) ($baseType?->portion_size ?? $type->portion_size ?? 1));
                $batches = intdiv($pricingQuantity, $portionSize);
                if ($recipe->isEmpty() || $batches < 1) {
                    $unresolvedTypes[] = $item['name'] . ' (missing SDE ice refining recipe or incomplete batch quantity)';
                    continue;
                }

                $refinedLineValue = 0.0;
                foreach ($recipe as $material) {
                    $output = $mineralTypes->get($material->material_type_id);
                    if (!$output) {
                        $unresolvedTypes[] = $item['name'] . ' (missing ice product type ID ' . $material->material_type_id . ')';
                        continue 2;
                    }
                    $outputName = mb_strtolower($output->type_name);
                    $yieldedQuantity = (int) $material->quantity * $batches;
                    if ($outputName === 'heavy water') {
                        $manualUnitPrice = (float) $icePricingSettings->get('ice_heavy_water_unit_price', 0);
                        if ($manualUnitPrice > 0) {
                            $unitOutputPrice = $manualUnitPrice;
                            $priceSource = 'admin-configured-heavy-water-unit-value';
                        } else {
                            $market = $this->liveJitaOrderPrice((int) $output->type_id, $regionId, 1, true);
                            if ($market === null) {
                                $unpricedTypes[] = 'Heavy Water (refined output for ' . $item['name'] . ': no Jita buy price and no manual unit value configured)';
                                continue 2;
                            }
                            $unitOutputPrice = $market['price'] * 0.10;
                            $priceSource = 'live-jita-buy-10-percent-default';
                        }
                        $buyPercent = null;
                    } else {
                        $settingKey = match (true) {
                            $outputName === 'nitrogen isotopes' => 'ice_refined_nitrogen_percent',
                            $outputName === 'liquid ozone' => 'ice_refined_ozone_percent',
                            str_starts_with($outputName, 'strontium') => 'ice_refined_strontium_percent',
                            default => null,
                        };
                        if ($settingKey === null) {
                            $unresolvedTypes[] = $item['name'] . ' (unexpected ice refining output: ' . $output->type_name . ')';
                            continue 2;
                        }
                        $buyPercent = (float) $icePricingSettings->get($settingKey, 100);
                        $market = $this->liveJitaOrderPrice((int) $output->type_id, $regionId, 1, true);
                        if ($market === null) {
                            $unpricedTypes[] = $output->type_name . ' (refined output for ' . $item['name'] . ': no current Jita buy price)';
                            continue 2;
                        }
                        $unitOutputPrice = $market['price'] * $buyPercent / 100;
                        $priceSource = 'live-jita-buy';
                    }
                    $grossValue = round($yieldedQuantity * $unitOutputPrice, 2);
                    $refinedLineValue += $grossValue;
                    $refinedMineralDetails[] = [
                        'type_id' => (int) $output->type_id,
                        'name' => $output->type_name,
                        'quantity' => $yieldedQuantity,
                        'unit_price' => $unitOutputPrice,
                        'buy_percent' => $buyPercent,
                        'price_source' => $priceSource,
                        'gross_value' => $grossValue,
                        'value' => $grossValue,
                    ];
                }
                $refinedLineValue = round($refinedLineValue, 2);
                $outputQuantity = null;
                $outputName = 'refined ice products';
                $valuationMode = 'ice_reprocessed';
                $unitPrice = $refinedLineValue / $pricingQuantity;
                $price = (object) ['source' => 'live-esi-ice-products-with-configured-heavy-water-value', 'observed_at' => now()];
                $lineValue = $refinedLineValue;
                $customPayoutPercent = 100.0;
                $customRateSource = 'ice_refined_outputs';
            } elseif ($isIce) {
                // Other ice types use their own live Jita buy price and individual rate.
                $marketPrice = $this->liveJitaOrderPrice((int) $type->type_id, $regionId, 50000, true);
                if ($marketPrice === null) {
                    $unpricedTypes[] = $item['name'] . ' (no current Jita buy price at 50,000-unit depth)';
                    continue;
                }
                $unitPrice = $marketPrice['price'];
                $price = (object) ['source' => 'live-esi-jita-50000-unit-depth', 'observed_at' => $marketPrice['observed_at']];
                $lineValue = round($unitPrice * $pricingQuantity, 2);
                $customPayoutPercent = (float) $icePricingSettings->get('ice_buy_percent_' . (int) $type->type_id, 87);
                $customRateSource = 'individual_ice';
                $valuationMode = 'ice_market';
            } elseif ($isHeavyWater) {
                // Heavy Water is discounted because of its hauling volume.
                $marketPrice = $this->liveJitaOrderPrice((int) $type->type_id, $regionId, 50000, true);
                if ($marketPrice === null) {
                    $unpricedTypes[] = 'Heavy Water (no current Jita buy price at 50,000-unit depth)';
                    continue;
                }
                $unitPrice = $marketPrice['price'];
                $price = (object) ['source' => 'live-esi-jita-50000-unit-depth', 'observed_at' => $marketPrice['observed_at']];
                $lineValue = round($unitPrice * $pricingQuantity, 2);
                $customPayoutPercent = 10.0;
                $customRateSource = 'heavy_water_fixed_10_percent';
            } elseif ((int) $type->category_id === 25) {
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
                    $mineralPrice = $this->liveJitaOrderPrice($mineralId, $regionId, $depth, true);
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
                $outputQuantity = count($refinedMineralDetails) === 1 ? (int) $refinedMineralDetails[0]['quantity'] : null;
                $outputName = count($refinedMineralDetails) === 1 ? $refinedMineralDetails[0]['name'] : 'refined minerals';
                $valuationMode = 'reprocessed';
                $unitPrice = $refinedLineValue / $pricingQuantity;
                $price = (object) ['source' => 'live-esi-refined-outputs', 'observed_at' => now()];
                $lineValue = $refinedLineValue;
            } else {
                $priceColumn = $side === 'sell' ? 'sell_price' : 'buy_price';
                if ($side === 'sell') {
                    $marketPrice = $this->liveJitaOrderPrice((int) $type->type_id, $regionId, 1, false);
                    $unitPrice = $marketPrice['price'] ?? null;
                    $price = $marketPrice ? (object) ['source' => 'live-esi-jita-4-4-sell-order', 'observed_at' => $marketPrice['observed_at']] : null;
                } else {
                    $marketPrice = $this->liveJitaOrderPrice((int) $type->type_id, $regionId, 50000, true);
                    $unitPrice = $marketPrice['price'] ?? null;
                    $price = $marketPrice ? (object) ['source' => 'live-esi-jita-50000-unit-depth', 'observed_at' => $marketPrice['observed_at']] : null;
                }
                if ($unitPrice === null || $unitPrice <= 0 || !$price) {
                    $unpricedTypes[] = $item['name'] . ' (type ID ' . $type->type_id . ': no valid Jita price)';
                    continue;
                }
                $lineValue = round($unitPrice * $pricingQuantity, 2);
            }

            // Customer-facing equivalent rate is calculated only for asteroid ore:
            // compare the actual mineral-based offer per submitted ore unit with
            // the current best Jita buy order for that same ore type. This is display
            // metadata only; it never changes the mineral valuation or payout.
            $oreJitaBuyPrice = null;
            $effectiveRatePercent = null;
            if ((int) $type->category_id === 25 && $valuationMode === 'reprocessed') {
                // Fetch the current ESI order book for the submitted ore.
                $oreMarket = $this->liveJitaOrderPrice((int) $type->type_id, $regionId, 1, true);
                $oreJitaBuyPrice = $oreMarket['price'] ?? null;
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
            $rate = $customPayoutPercent !== null
                ? $customPayoutPercent
                : ((int) $type->category_id === 25 && !$isIce
                    ? 100.0
                    : ($fixedPayoutPercent !== null
                        ? $fixedPayoutPercent
                        : ($hasSpecial87Percent ? 87.0 : (float) ($piBuyRule->buy_percent ?? $payoutRule->payout_percent ?? $defaultRate))));
            // Normalize every existing 90% payout rule to 87%, including
            // category rules, individual PI rules, and explicitly fixed rates.
            if (abs($rate - 90.0) < 0.000001) {
                $rate = 87.0;
            }

            $payoutValue = round($lineValue * $rate / 100, 2);
            if ($oreJitaBuyPrice !== null && $oreJitaBuyPrice > 0 && $pricingQuantity > 0) {
                $effectiveRatePercent = round(($payoutValue / ($pricingQuantity * $oreJitaBuyPrice)) * 100, 4);
            }

            $lines[] = [
                'type_id' => (int) $type->type_id,
                'type_name' => $type->type_name,
                'quantity' => (int) $item['quantity'],
                // Equivalent value per submitted unit, including reprocessing conversions.
                'unit_price' => round($lineValue / (int) $item['quantity'], 4),
                'line_value' => $lineValue,
                'payout_percent' => $rate,
                'payout_value' => $payoutValue,
                'ore_jita_buy_price' => $oreJitaBuyPrice,
                'effective_rate_percent' => $effectiveRatePercent,
                'category_id' => $effectiveCategoryId,
                'category_name' => $effectiveCategoryName,
                'rate_source' => $customRateSource ?? ($fixedPayoutPercent !== null ? 'fixed' : ($piBuyRule ? 'individual_pi' : ($payoutRule ? 'category' : 'default'))),
                'source' => $price->source,
                'observed_at' => $price->observed_at,
                'valuation_mode' => $valuationMode,
                'output_type_id' => $valuationMode === 'reprocessed' ? (int) $pricingType->type_id : null,
                'output_name' => $valuationMode === 'reprocessed' ? $outputName : null,
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
            $priceErrors[] = 'These item types exist in the SDE but have no current positive Jita ' . $side . ' price: ' . implode(', ', array_unique($unpricedTypes)) . '. Refresh their Jita prices before appraising.';
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

    /**
     * Price this appraisal from the current ESI order book, never from historical
     * market snapshots or previously stored order-book rows. ESI may briefly cache
     * its public market endpoint; the appraisal stores the returned price as history.
     */
    private function liveJitaOrderPrice(int $typeId, string $regionId, int $requiredDepth, bool $buyOrder): ?array
    {
        $key = $regionId . ':' . $typeId;
        if (!array_key_exists($key, $this->liveOrderBooks)) {
            $http = Http::withHeaders([
                'User-Agent' => config('buyback.eve_user_agent'),
                'X-Compatibility-Date' => config('buyback.esi_compatibility_date'),
            ])->acceptJson()->timeout(30)->retry(2, 500);

            $stationId = (int) config('buyback.jita_station_id', 60003760);
            $pages = 1;
            $books = ['buy' => [], 'sell' => []];
            try {
                for ($page = 1; $page <= $pages; $page++) {
                    $response = $http->get("https://esi.evetech.net/latest/markets/{$regionId}/orders/", [
                        'order_type' => 'all',
                        'type_id' => $typeId,
                        'page' => $page,
                    ])->throw();
                    $pages = min(1000, max(1, (int) $response->header('X-Pages', 1)));
                    foreach ($response->json() as $order) {
                        if ((int) ($order['location_id'] ?? 0) !== $stationId) continue;
                        $price = (float) ($order['price'] ?? 0);
                        $volume = (int) ($order['volume_remain'] ?? 0);
                        if ($price <= 0 || $volume < 1) continue;
                        $side = (bool) ($order['is_buy_order'] ?? false) ? 'buy' : 'sell';
                        $books[$side][] = ['price' => $price, 'volume' => $volume];
                    }
                }
            } catch (\Throwable $exception) {
                report($exception);
                return null;
            }

            usort($books['buy'], fn (array $a, array $b) => $b['price'] <=> $a['price']);
            usort($books['sell'], fn (array $a, array $b) => $a['price'] <=> $b['price']);
            $this->liveOrderBooks[$key] = [
                ...$books,
                'observed_at' => now()->toDateTimeString(),
            ];
        }

        $side = $buyOrder ? 'buy' : 'sell';
        $remaining = max(1, $requiredDepth);
        foreach ($this->liveOrderBooks[$key][$side] as $order) {
            if ($order['volume'] >= $remaining) {
                return ['price' => $order['price'], 'observed_at' => $this->liveOrderBooks[$key]['observed_at']];
            }
            $remaining -= $order['volume'];
        }

        return null;
    }
}
