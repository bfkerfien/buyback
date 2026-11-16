<?php

namespace App\Services\Appraisal;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AppraisalCalculator
{
    public function calculate(array $parsedItems, ?string $requiredCategoryName = null, ?float $fixedPayoutPercent = null, bool $ignoreNonMatching = false): array
    {
        $names = array_column($parsedItems, 'name');
        $types = DB::table('eve_item_types as t')
            ->leftJoin('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->where('t.published', true)->whereIn('t.type_name', $names)
            ->get(['t.type_id','t.type_name','t.group_id','g.category_id','g.category_name'])
            ->keyBy(fn ($type) => mb_strtolower($type->type_name));

        $oreRules = DB::table('appraisal_ore_valuation_rules')->get()->keyBy('ore_type_id');
        $outputIds = $oreRules->where('valuation_mode', 'reprocessed')->pluck('output_type_id')->filter()->unique()->values();
        $outputTypes = $outputIds->isEmpty() ? collect() : DB::table('eve_item_types as t')
            ->leftJoin('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->whereIn('t.type_id', $outputIds)->where('t.published', true)
            ->get(['t.type_id','t.type_name','t.group_id','g.category_id','g.category_name'])
            ->keyBy('type_id');

        $rules = DB::table('appraisal_payout_rules')->get()->keyBy('category_id');
        $piBuyRules = DB::table('appraisal_pi_buy_rules')->get()->keyBy('type_id');
        $unresolvedTypes = [];
        $unpricedTypes = [];
        $lines = [];
        $side = config('buyback.price_side', 'buy');
        $defaultRate = (float) config('buyback.payout_percent', 90);
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
            $valuationMode = 'compressed';
            $outputQuantity = null;
            $conversionRatio = null;

            $oreRule = $oreRules->get($type->type_id);
            if ($oreRule && $oreRule->valuation_mode === 'reprocessed') {
                $pricingType = $outputTypes->get($oreRule->output_type_id);
                if (!$pricingType) {
                    $unresolved[] = $item['name'] . ' (missing reprocessed output item)';
                    continue;
                }

                $conversionRatio = (float) $oreRule->output_quantity_per_input;
                $outputQuantity = (int) floor($pricingQuantity * $conversionRatio + 0.00000001);
                if ($outputQuantity < 1) {
                    $unresolved[] = $item['name'] . ' (reprocessed output rounds to zero)';
                    continue;
                }

                $pricingQuantity = $outputQuantity;
                $valuationMode = 'reprocessed';
            }

            // A newer market snapshot can legitimately contain a null buy or sell
            // side. Select the newest positive price for the configured side instead
            // of letting a null latest snapshot hide an older valid Jita quote.
            $priceColumn = $side === 'sell' ? 'sell_price' : 'buy_price';
            $price = DB::table('market_price_snapshots')
                ->where('type_id', $pricingType->type_id)
                ->where('region_id', $regionId)
                ->whereNotNull($priceColumn)
                ->where($priceColumn, '>', 0)
                ->orderByDesc('observed_at')
                ->first(['buy_price','sell_price','source','observed_at']);
            $unitPrice = $price ? (float) $price->{$priceColumn} : 0.0;
            if ($unitPrice <= 0) {
                $unpricedTypes[] = $valuationMode === 'reprocessed'
                    ? $item['name'] . ' (type ID ' . $pricingType->type_id . ': no Jita ' . $side . ' price; refresh market prices)'
                    : $item['name'] . ' (type ID ' . $type->type_id . ': no Jita ' . $side . ' price; refresh market prices)';
                continue;
            }

            $lineValue = round($unitPrice * $pricingQuantity, 2);
            $effectiveCategoryId = $pricingType->category_id;
            $effectiveCategoryName = $pricingType->category_name;
            $payoutRule = $effectiveCategoryId ? $rules->get($effectiveCategoryId) : null;
            // P1 planetary commodities use their individually configured appraisal
            // buy percentage instead of the shared PI category payout percentage.
            $piBuyRule = ((int) $type->category_id === 43 && (int) $type->group_id === 1042)
                ? $piBuyRules->get($type->type_id)
                : null;
            $rate = $fixedPayoutPercent !== null
                ? $fixedPayoutPercent
                : (float) ($piBuyRule->buy_percent ?? $payoutRule->payout_percent ?? $defaultRate);

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
}
