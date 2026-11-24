<?php

namespace App\Services\Appraisal;

use App\Services\Eve\ExcludedMissionOre;
use App\Services\JitaMarketPriceRefresher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AppraisalCalculator
{
    public function __construct(private readonly JitaMarketPriceRefresher $priceRefresher)
    {
    }

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
            ->get(['t.type_id','t.type_name','t.group_id','t.meta_group_id','t.portion_size','g.category_id','g.category_name','g.group_name'])
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

                $piBuyRules = DB::table('appraisal_pi_buy_rules')->get()->keyBy('type_id');
        $itemPriceMultipliers = DB::table('appraisal_item_price_overrides')->pluck('price_multiplier', 'type_id');
        $mineralPricingSettings = DB::table('buyback_system_status')->where('status_key', 'like', 'mineral_buy_%')->pluck('status_value', 'status_key');
        $icePricingSettings = DB::table('buyback_system_status')->where('status_key', 'like', 'ice_%')->pluck('status_value', 'status_key');
        $gasPricingSettings = DB::table('buyback_system_status')->where('status_key', 'like', 'gas_%')->pluck('status_value', 'status_key');
        $compressedGasTypes = DB::table('eve_item_types as t')
            ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->where('t.published', true)
            ->where('g.group_name', 'Compressed Gas')
            ->get(['t.type_id', 't.type_name'])
            ->keyBy(fn ($gas) => mb_strtolower($gas->type_name));
        $unresolvedTypes = [];
        $unpricedTypes = [];
        $unpricedItemReasons = [];
        $lines = [];
        $side = config('buyback.price_side', 'buy');
        $defaultRate = 87.0;
        $regionId = (string) config('buyback.region_id', '10000002');

        $appraisedTypeIds = $types->filter(fn ($type) => $requiredCategoryName === null
                || mb_strtolower((string) $type->category_name) === mb_strtolower($requiredCategoryName))
            ->pluck('type_id');
        $this->priceRefresher->registerForPriceRefresh($appraisedTypeIds->all());
        $this->priceRefresher->registerForPriceRefresh($mineralIds->all());

        foreach ($parsedItems as $item) {
            $type = $types->get(mb_strtolower($item['name']));
            if (!$type) {
                if (!$ignoreNonMatching) $unresolvedTypes[] = $item['name'];
                continue;
            }

            $itemPriceMultiplier = (float) $itemPriceMultipliers->get((int) $type->type_id, 1.0);
            $priceMultiplierBaseValue = 0.0;
            $priceMultiplierFixedCost = 0.0;

            if ($requiredCategoryName !== null && mb_strtolower((string) $type->category_name) !== mb_strtolower($requiredCategoryName)) {
                if (!$ignoreNonMatching) $unresolved[] = $item['name'] . ' (not in the ' . $requiredCategoryName . ' category)';
                continue;
            }

            $pricingType = $type;
            $pricingQuantity = (int) $item['quantity'];
            if (str_starts_with(mb_strtolower($type->type_name), 'compressed ')
                && (int) $oreRecipeTypeIds->get((int) $type->type_id, 0) === 0) {
                $unresolvedTypes[] = $item['name'] . ' (matching uncompressed base item is missing from the SDE)';
                continue;
            }
            $valuationMode = 'market';
            $outputQuantity = null;
            $conversionRatio = null;
            $refinedLineValue = null;
            $refinedMineralDetails = [];
            $customPayoutPercent = null;
            $customRateSource = null;
            $depth = null;
            $isIce = in_array(mb_strtolower((string) ($type->group_name ?? '')), ['ice', 'compressed ice'], true);
            $baseTypeName = str_starts_with(mb_strtolower($type->type_name), 'compressed ')
                ? trim(mb_substr($type->type_name, mb_strlen('Compressed ')))
                : $type->type_name;
            $compressedGasName = str_starts_with(mb_strtolower($type->type_name), 'compressed ')
                ? $type->type_name
                : 'Compressed ' . $type->type_name;
            $compressedGasType = $compressedGasTypes->get(mb_strtolower($compressedGasName));
            $isGas = $compressedGasType !== null;
            $isIceProduct = in_array(mb_strtolower($type->type_name), [
                'nitrogen isotopes',
                'heavy water',
                'strontium clathrates',
                'liquid ozone',
            ], true);

            // Regional/faction ice is identified by its SDE reprocessing recipe
            // containing one of the four faction isotope products. Both the base
            // ore and its enriched/IV-Grade variant then use the same refined-output
            // valuation path as asteroid and moon ore. Standard ice without an
            // isotope output (Glare Crust, Dark Glitter, Gelidus, Krystallos)
            // retains its individually configured direct-market valuation.
            $iceRecipeTypeId = (int) $oreRecipeTypeIds->get((int) $type->type_id, (int) $type->type_id);
            $iceRecipe = $oreMaterials->get($iceRecipeTypeId, collect());
            $factionIsotopeNames = [
                'helium isotopes',
                'hydrogen isotopes',
                'nitrogen isotopes',
                'oxygen isotopes',
            ];
            $hasFactionIsotopeOutput = $isIce && $iceRecipe->contains(function ($material) use ($mineralTypes, $factionIsotopeNames) {
                $output = $mineralTypes->get((int) $material->material_type_id);
                return $output && in_array(mb_strtolower($output->type_name), $factionIsotopeNames, true);
            });

            // Every gas type is valued against the stored Jita buy snapshot for its
            // compressed counterpart. Only the configured payout percentage differs
            // between compressed and uncompressed inventory submissions.
            if ($isGas) {
                $depth = max(1, (int) $gasPricingSettings->get('gas_buy_depth', 50000));
                $marketPrice = $this->snapshotJitaPrice((int) $compressedGasType->type_id, $regionId, 'buy', $depth);
                if ($marketPrice === null) {
                    $unpricedTypes[] = $item['name'] . ' (no stored Jita buy-price snapshot for its compressed gas reference)';
                    $unpricedItemReasons[$item['name']] = 'No stored compressed-gas Jita buy-price snapshot is available yet.';
                    continue;
                }
                $unitPrice = $marketPrice['price'] * $itemPriceMultiplier;
                $price = (object) [
                    'source' => 'database-snapshot-jita-buy-' . $depth . '-unit-depth',
                    'observed_at' => $marketPrice['observed_at'],
                ];
                $lineValue = round($unitPrice * $pricingQuantity, 2);
                $isCompressedGas = str_starts_with(mb_strtolower($type->type_name), 'compressed ');
                $gasRateKey = $isCompressedGas
                    ? 'gas_compressed_percent_' . (int) $type->type_id
                    : 'gas_uncompressed_percent_' . (int) $type->type_id;
                $globalGasRateKey = $isCompressedGas ? 'gas_compressed_percent' : 'gas_uncompressed_percent';
                // Per-type overrides let admins tune individual raw gas prices
                // against Holo without changing other gas types. The global rate
                // remains the fallback for types without an individual override.
                $customPayoutPercent = $gasPricingSettings->has($gasRateKey)
                    ? (float) $gasPricingSettings->get($gasRateKey)
                    : null;
                $customRateSource = $isCompressedGas ? 'compressed_gas' : 'uncompressed_gas';
                $valuationMode = 'gas_market';
            } elseif ($isIce && !$hasFactionIsotopeOutput) {
                // Standard ice without faction isotope outputs is valued directly
                // from its stored Jita buy-price snapshot, preserving its individual rate.
                $typeId = (int) $type->type_id;
                $depth = max(1, (int) $icePricingSettings->get('ice_buy_depth_' . $typeId, 1000));
                $marketPrice = $this->snapshotJitaPrice($typeId, $regionId, 'buy', $depth);

                if ($marketPrice === null) {
                    $unpricedTypes[] = $item['name']
                        . ' (no stored Jita buy-price snapshot is available yet)';
                    $unpricedItemReasons[$item['name']] = 'No stored Jita buy-price snapshot is available yet.';
                    continue;
                }

                $unitPrice = $marketPrice['price'] * $itemPriceMultiplier;
                $price = (object) [
                    'source' => 'database-snapshot-jita-buy-' . $depth . '-unit-quantity',
                    'observed_at' => $marketPrice['observed_at'],
                ];
                $lineValue = round($unitPrice * $pricingQuantity, 2);
                $customPayoutPercent = (float) $icePricingSettings->get('ice_buy_percent_' . $typeId, 87);
                $customRateSource = 'individual_ice';
                $valuationMode = 'ice_market';
            } elseif ($isIceProduct) {
                // Refined ice outputs (isotopes, Heavy Water, Liquid Ozone and
                // Strontium Clathrates) remain directly valued from their own
                // Jita buy orders. Regional ice ores themselves are handled by
                // the shared SDE reprocessing path below, like asteroid and moon ore.
                $typeId = (int) $type->type_id;
                $depth = max(1, (int) $icePricingSettings->get(
                    'ice_buy_depth_' . $typeId,
                    1000
                ));
                $marketPrice = $this->snapshotJitaPrice($typeId, $regionId, 'buy', $depth);

                if ($marketPrice === null) {
                    $unpricedTypes[] = $item['name']
                        . ' (insufficient live Jita buy volume at configured '
                        . number_format($depth) . '-unit quantity)';
                    $unpricedItemReasons[$item['name']] = 'No stored Jita buy-price snapshot is available yet.';
                    continue;
                }

                $unitPrice = $marketPrice['price'] * $itemPriceMultiplier;
                $price = (object) [
                    'source' => 'database-snapshot-jita-buy-' . $depth . '-unit-quantity',
                    'observed_at' => $marketPrice['observed_at'],
                ];
                $lineValue = round($unitPrice * $pricingQuantity, 2);

                $defaultProductPercent = mb_strtolower($type->type_name) === 'heavy water'
                    ? 10
                    : 100;

                $legacyPercentKey = match (mb_strtolower($type->type_name)) {
                    'nitrogen isotopes' => 'ice_refined_nitrogen_percent',
                    'liquid ozone' => 'ice_refined_ozone_percent',
                    'strontium clathrates' => 'ice_refined_strontium_percent',
                    default => null,
                };

                $legacyPercent = $legacyPercentKey !== null
                    ? (float) $icePricingSettings->get($legacyPercentKey, $defaultProductPercent)
                    : $defaultProductPercent;

                $customPayoutPercent = $icePricingSettings->has('ice_buy_percent_' . $typeId)
                    ? (float) $icePricingSettings->get('ice_buy_percent_' . $typeId)
                    : null;
                $customRateSource = 'individual_ice_product';
                $valuationMode = 'ice_product_market';
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
                    // Ice refining outputs have their own admin-configured buyback
                    // percentage and Jita depth. In particular, Heavy Water must use
                    // ice_buy_percent_<typeId>; previously the ore-refining path
                    // ignored that setting and always fell back to mineral pricing.
                    $isIceRefiningProduct = in_array(mb_strtolower($mineral->type_name), [
                        'heavy water',
                        'liquid ozone',
                        'strontium clathrates',
                        'nitrogen isotopes',
                        'oxygen isotopes',
                        'hydrogen isotopes',
                        'helium isotopes',
                    ], true);

                    if ($isIceRefiningProduct) {
                        $depth = max(1, (int) $icePricingSettings->get('ice_buy_depth_' . $mineralId, 1000));
                        $buyPercent = (float) $icePricingSettings->get('ice_buy_percent_' . $mineralId, 87);
                    } else {
                        $depth = max(1, (int) $mineralPricingSettings->get('mineral_buy_depth_' . $mineralId, $defaultDepths[$mineral->type_name] ?? 50000));
                        $buyPercent = (float) $mineralPricingSettings->get('mineral_buy_percent_' . $mineralId, 87);
                    }

                    $mineralPrice = $this->snapshotJitaPrice($mineralId, $regionId, 'buy', $depth);
                    if ($mineralPrice === null) {
                        $unpricedTypes[] = $mineral->type_name . ' (refined output for ' . $item['name'] . ': no stored Jita buy-price snapshot)';
                        $unpricedItemReasons[$item['name']] = 'Missing Jita price for refined output ' . $mineral->type_name . '.';
                        continue 2;
                    }
                    $priceMultiplierBaseValue += round($yieldedQuantity * $mineralPrice['price'] * $buyPercent / 100, 2);
                    $grossMineralValue = round($yieldedQuantity * $mineralPrice['price'] * $itemPriceMultiplier, 2);
                    $mineralValue = round($grossMineralValue * $buyPercent / 100, 2);
                    $mineralVolume = (float) ($mineral->volume ?? 0);
                    $shippingVolume = $yieldedQuantity * $mineralVolume;
                    $shippingCostPerM3 = max(0, (float) DB::table('buyback_system_status')->where('status_key', 'ore_shipping_cost_per_m3')->value('status_value', 350));
                    $shippingCost = round($shippingVolume * $shippingCostPerM3, 2);
                    $priceMultiplierFixedCost += $shippingCost;
                    $refinedLineValue += $mineralValue - $shippingCost;
                    $refinedMineralDetails[] = [
                        'type_id' => $mineralId,
                        'name' => $mineral->type_name,
                        'quantity' => $yieldedQuantity,
                        'unit_price' => $mineralPrice['price'],
                        'buy_percent' => $buyPercent,
                        'price_depth' => $depth,
                        'observed_at' => $mineralPrice['observed_at'],
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
                $price = (object) ['source' => 'database-snapshot-refined-outputs', 'observed_at' => collect($refinedMineralDetails)->pluck('observed_at')->filter()->max()];
                $lineValue = $refinedLineValue;
            } else {
                $isDirectMineralForDepth = mb_strtolower((string) ($type->category_name ?? '')) === 'material'
                    && mb_strtolower((string) ($type->group_name ?? '')) === 'mineral';
                if ($isDirectMineralForDepth) {
                    $defaultMineralDepths = [
                        'Tritanium' => 150000000, 'Pyerite' => 30000000, 'Mexallon' => 3000000,
                        'Isogen' => 2000000, 'Nocxium' => 1000000, 'Zydrine' => 500000,
                        'Megacyte' => 500000, 'Morphite' => 45000,
                    ];
                    $depth = max(1, (int) $mineralPricingSettings->get(
                        'mineral_buy_depth_' . (int) $type->type_id,
                        $defaultMineralDepths[$type->type_name] ?? 50000
                    ));
                }
                $priceColumn = $side === 'sell' ? 'sell_price' : 'buy_price';
                if ($side === 'sell') {
                    $marketPrice = $this->snapshotJitaPrice((int) $type->type_id, $regionId, 'sell', $depth);
                    $unitPrice = $marketPrice['price'] ?? null;
                    $price = $marketPrice ? (object) ['source' => 'database-snapshot-jita-sell', 'observed_at' => $marketPrice['observed_at']] : null;
                } else {
                    $marketPrice = $this->snapshotJitaPrice((int) $type->type_id, $regionId, 'buy', $depth);
                    $unitPrice = $marketPrice['price'] ?? null;
                    $price = $marketPrice ? (object) ['source' => 'database-snapshot-jita-buy', 'observed_at' => $marketPrice['observed_at']] : null;
                }
                if ($unitPrice === null || $unitPrice <= 0 || !$price) {
                    $unpricedTypes[] = $item['name'] . ' (type ID ' . $type->type_id . ': no valid Jita price)';
                    $unpricedItemReasons[$item['name']] = 'No stored Jita market-price snapshot is available yet.';
                    continue;
                }
                $unitPrice = (float) $unitPrice * $itemPriceMultiplier;
                $lineValue = round($unitPrice * $pricingQuantity, 2);
            }

            // Customer-facing equivalent rate is calculated only for asteroid ore:
            // compare the actual mineral-based offer per submitted ore unit with
            // the current best Jita buy order for that same ore type. This is display
            // metadata only; it never changes the mineral valuation or payout.
            $oreJitaBuyPrice = null;
            $effectiveRatePercent = null;
            if ((int) $type->category_id === 25 && !$isIce && $valuationMode === 'reprocessed') {
                // Read the latest stored Jita buy snapshot for display metadata.
                $oreMarket = $this->snapshotJitaPrice((int) $type->type_id, $regionId, 'buy');
                $oreJitaBuyPrice = $oreMarket['price'] ?? null;
            }

            // Direct appraisals of standard minerals must use the same
            // per-mineral payout setting as minerals produced by ore refining.
            // Otherwise a mineral submitted on its own falls through to the
            // Material category/default rate and can show a different percentage.
            $isDirectMineral = mb_strtolower((string) ($type->category_name ?? '')) === 'material'
                && mb_strtolower((string) ($type->group_name ?? '')) === 'mineral';
            if ($isDirectMineral) {
                $customPayoutPercent = $mineralPricingSettings->has('mineral_buy_percent_' . (int) $type->type_id)
                    ? (float) $mineralPricingSettings->get('mineral_buy_percent_' . (int) $type->type_id)
                    : null;
                $customRateSource = 'individual_mineral';
            }

            // Individual item percentages win. Anything without an individual
            // percentage uses the single 87% default; category pricing is disabled.
            $piBuyRule = ((int) $type->category_id === 43
                && in_array((int) $type->group_id, [1042, 1034, 1040, 1041], true))
                ? $piBuyRules->get($type->type_id)
                : null;
            // Reprocessed ore/faction ice has already had each refined
            // mineral's individual buyback percentage applied above. Applying
            // the generic item payout rate again here double-discounts the
            // entire refined basket (e.g. 87% × 87% = 75.69%).
            // Its final rate must therefore be 100%; all pricing/rates are
            // already reflected in $lineValue.
            $isTechTwoShip = mb_strtolower((string) ($type->category_name ?? '')) === 'ship'
                && (int) ($type->meta_group_id ?? 0) === 2;
            $rate = $valuationMode === 'reprocessed'
                ? 100.0
                : ($isTechTwoShip
                    ? 91.5
                    : ($customPayoutPercent
                        ?? ($fixedPayoutPercent !== null ? $fixedPayoutPercent : (float) ($piBuyRule->buy_percent ?? $defaultRate))));
            $effectiveCategoryId = $pricingType->category_id;
            $effectiveCategoryName = $pricingType->category_name;

            if ($valuationMode !== 'reprocessed') {
                $priceMultiplierBaseValue = round(($lineValue / $itemPriceMultiplier) * $rate / 100, 2);
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
                'item_price_multiplier' => $itemPriceMultiplier,
                'price_multiplier_base_value' => $priceMultiplierBaseValue,
                'price_multiplier_fixed_cost' => $priceMultiplierFixedCost,
                'payout_value' => $payoutValue,
                'ore_jita_buy_price' => $oreJitaBuyPrice,
                'effective_rate_percent' => $effectiveRatePercent,
                'category_id' => $effectiveCategoryId,
                'category_name' => $effectiveCategoryName,
                'meta_group_id' => (int) ($type->meta_group_id ?? 0),
                'rate_source' => $customRateSource ?? ($fixedPayoutPercent !== null ? 'fixed' : ($piBuyRule ? 'individual_pi' : 'default')),
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

        // Keep every recognized item visible in the submitted appraisal. If its
        // current market price is unavailable, save a zero-value line with an
        // explicit marker so the member can exclude it and submit a fresh quote.
        foreach ($unpricedItemReasons as $itemName => $reason) {
            $type = $types->get(mb_strtolower($itemName));
            if (!$type) continue;
            $rate = (float) config('buyback.payout_percent', 87);
            $lines[] = [
                'type_id' => (int) $type->type_id,
                'type_name' => $type->type_name,
                'quantity' => (int) (collect($parsedItems)->firstWhere('name', $itemName)['quantity'] ?? 0),
                'unit_price' => 0,
                'line_value' => 0,
                'payout_percent' => $rate,
                'payout_value' => 0,
                'ore_jita_buy_price' => null,
                'effective_rate_percent' => null,
                'category_id' => $type->category_id,
                'category_name' => $type->category_name,
                'rate_source' => 'missing_price',
                'source' => 'missing-market-price',
                'observed_at' => null,
                'valuation_mode' => 'missing_price',
                'output_type_id' => null,
                'output_name' => null,
                'output_quantity' => null,
                'conversion_ratio' => null,
                'output_unit_price' => 0,
                'refined_materials' => [],
                'missing_price' => true,
                'missing_price_reason' => $reason,
            ];
        }

        $priceErrors = [];
        if ($unresolvedTypes) {
            $priceErrors[] = 'These item types are missing from the imported published EVE SDE: ' . implode(', ', array_unique($unresolvedTypes)) . '. Re-import CCP types.jsonl and taxonomy.';
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

    /** Read a best price or the marginal price at a configured Jita order-book depth. */
    private function snapshotJitaPrice(int $typeId, string $regionId, string $side, ?int $depth = null): ?array
    {
        // A configured depth means the marginal price at the Nth unit in the
        // Jita IV - Moon 4 order book, not the best top-of-book price. This is
        // what the per-mineral depth controls in the admin pricing panel mean.
        if ($depth !== null && $depth > 1) {
            $isBuy = $side !== 'sell';
            $locationId = (int) config('buyback.jita_station_id', 60003760);
            $latest = DB::table('market_order_book_snapshots')
                ->where('type_id', $typeId)
                ->where('region_id', $regionId)
                ->where('location_id', $locationId)
                ->where('is_buy_order', $isBuy)
                ->max('observed_at');

            if (!$latest) {
                return null;
            }

            $orders = DB::table('market_order_book_snapshots')
                ->where('type_id', $typeId)
                ->where('region_id', $regionId)
                ->where('location_id', $locationId)
                ->where('is_buy_order', $isBuy)
                ->where('observed_at', $latest)
                ->orderBy('price', $isBuy ? 'desc' : 'asc')
                ->get(['price', 'volume_remain']);

            $remaining = $depth;
            foreach ($orders as $order) {
                $volume = (int) $order->volume_remain;
                if ($volume >= $remaining) {
                    return ['price' => (float) $order->price, 'observed_at' => $latest];
                }
                $remaining -= $volume;
            }

            // Never silently substitute the best order when the configured
            // depth is unavailable; that would materially overstate value.
            return null;
        }

        $column = $side === 'sell' ? 'sell_price' : 'buy_price';
        $snapshotIdColumn = $side === 'sell' ? 'sell_snapshot_id' : 'buy_snapshot_id';
        $observedAtColumn = $side === 'sell' ? 'sell_observed_at' : 'buy_observed_at';
        $snapshot = DB::table('market_price_current')
            ->where('type_id', $typeId)
            ->where('region_id', $regionId)
            ->whereNotNull($snapshotIdColumn)
            ->whereNotNull($column)
            ->where($column, '>', 0)
            ->orderByDesc($snapshotIdColumn)
            ->first([$column, $observedAtColumn]);

        if (!$snapshot) {
            return null;
        }

        return [
            'price' => (float) $snapshot->{$column},
            'observed_at' => $snapshot->{$observedAtColumn},
        ];
    }
}
