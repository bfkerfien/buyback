<?php

namespace App\\Services\\Appraisal;

use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Validation\\ValidationException;

final class AppraisalCalculator
{
    public function calculate(array $parsedItems): array
    {
        $names = array_column($parsedItems, 'name');
        $types = DB::table('eve_item_types as t')
            ->leftJoin('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->where('t.published', true)->whereIn('t.type_name', $names)
            ->get(['t.type_id','t.type_name','t.group_id','g.category_id','g.category_name'])
            ->keyBy(fn ($type) => mb_strtolower($type->type_name));
        $rules = DB::table('appraisal_payout_rules')->get()->keyBy('category_id');
        $unresolved = []; $lines = [];
        $side = config('buyback.price_side', 'buy');
        $defaultRate = (float) config('buyback.payout_percent', 90);

        foreach ($parsedItems as $item) {
            $type = $types->get(mb_strtolower($item['name']));
            if (!$type) { $unresolved[] = $item['name']; continue; }
            $price = DB::table('market_price_snapshots')->where('type_id', $type->type_id)
                ->where('region_id', (string) config('buyback.region_id', '10000002'))
                ->orderByDesc('observed_at')->first(['buy_price','sell_price','source','observed_at']);
            $unitPrice = $price ? (float) ($side === 'sell' ? $price->sell_price : $price->buy_price) : 0.0;
            if ($unitPrice <= 0) { $unresolved[] = $item['name'] . ' (no Jita ' . $side . ' price)'; continue; }

            $lineValue = round($unitPrice * $item['quantity'], 2);
            $rule = $type->category_id ? $rules->get($type->category_id) : null;
            $rate = (float) ($rule->payout_percent ?? $defaultRate);
            $lines[] = [
                'type_id'=>(int)$type->type_id,'type_name'=>$type->type_name,
                'quantity'=>(int)$item['quantity'],'unit_price'=>$unitPrice,'line_value'=>$lineValue,
                'payout_percent'=>$rate,'payout_value'=>round($lineValue*$rate/100,2),
                'category_id'=>$type->category_id,'category_name'=>$type->category_name,
                'rate_source'=>$rule ? 'category' : 'default',
                'source'=>$price->source,'observed_at'=>$price->observed_at,
            ];
        }

        if ($unresolved) throw ValidationException::withMessages(['inventory'=>[
            'Could not price these items: '.implode(', ',array_unique($unresolved)).'. Import EVE item data and refresh Jita prices before appraising.'
        ]]);

        $gross=round(array_sum(array_column($lines,'line_value')),2);
        $payout=round(array_sum(array_column($lines,'payout_value')),2);
        return ['lines'=>$lines,'gross_value'=>$gross,'fees'=>round($gross-$payout,2),'payout_value'=>$payout,
            'price_side'=>$side,'payout_percent'=>$defaultRate,'region_id'=>(string)config('buyback.region_id','10000002')];
    }
}
