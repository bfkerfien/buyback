<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayoutRuleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'min:1'],
            'payout_percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
        ]);
        $category = DB::table('eve_item_groups')->where('category_id', $data['category_id'])->whereNotIn('category_name', ['Asteroid', 'Material'])->orderBy('category_name')->first(['category_id','category_name']);
        if (!$category) throw ValidationException::withMessages(['category_id'=>['That EVE category was not found. Import the CCP SDE categories and groups first.']]);

        DB::table('appraisal_payout_rules')->updateOrInsert(
            ['category_id'=>$category->category_id],
            ['category_name'=>$category->category_name,'payout_percent'=>$data['payout_percent'],'updated_at'=>now(),'created_at'=>now()]
        );
        DB::table('audit_logs')->insert([
            'user_id'=>$request->user()->id,'action'=>'appraisal_category_rate.saved',
            'subject_type'=>'eve_item_category','subject_id'=>$category->category_id,
            'details'=>json_encode(['category'=>$category->category_name,'payout_percent'=>(float)$data['payout_percent']]),
            'ip_address'=>$request->ip(),'created_at'=>now(),'updated_at'=>now(),
        ]);
        return back()->with('status','Buyback rate saved for the '.$category->category_name.' category.');
    }

    public function storeMineralPricing(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'type_id' => ['required', 'integer', 'min:1'],
            'buy_percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'price_depth' => ['required', 'integer', 'min:1', 'max:2000000000'],
        ]);

        $material = DB::table('eve_item_types as t')
            ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->where('t.type_id', $data['type_id'])
            ->where('t.published', true)
            ->where('g.category_name', 'Material')
            ->whereIn('g.group_name', ['Mineral', 'Moon Materials'])
            ->first(['t.type_id', 't.type_name']);

        if (!$material) {
            throw ValidationException::withMessages(['type_id' => ['That item is not a published standard or moon-ore refining material.']]);
        }

        foreach ([
            'mineral_buy_percent_' . $material->type_id => (string) $data['buy_percent'],
            'mineral_buy_depth_' . $material->type_id => (string) $data['price_depth'],
        ] as $key => $value) {
            DB::table('buyback_system_status')->updateOrInsert(
                ['status_key' => $key],
                ['status_value' => $value]
            );
        }

        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => 'mineral_buyback_setting.saved',
            'subject_type' => 'eve_refining_material',
            'subject_id' => $material->type_id,
            'details' => json_encode([
                'material' => $material->type_name,
                'buy_percent' => (float) $data['buy_percent'],
                'price_depth' => (int) $data['price_depth'],
            ]),
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', $material->type_name . ' buy percentage and Jita depth saved.');
    }

    public function storeIcePricing(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        if ($request->boolean('save_refined_settings')) {
            $data = $request->validate([
                'nitrogen_percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
                'ozone_percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
                'strontium_percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
                'heavy_water_unit_price' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            ]);
            $settings = [
                'ice_refined_nitrogen_percent' => (string) $data['nitrogen_percent'],
                'ice_refined_ozone_percent' => (string) $data['ozone_percent'],
                'ice_refined_strontium_percent' => (string) $data['strontium_percent'],
                'ice_heavy_water_unit_price' => (string) $data['heavy_water_unit_price'],
            ];
            foreach ($settings as $key => $value) {
                DB::table('buyback_system_status')->updateOrInsert(['status_key' => $key], ['status_value' => $value]);
            }
            DB::table('audit_logs')->insert([
                'user_id' => $request->user()->id,
                'action' => 'ice_refined_pricing.saved',
                'subject_type' => 'ice_refined_outputs',
                'subject_id' => null,
                'details' => json_encode($data),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return back()->with('status', 'White Glaze and Pristine White Glaze refined-output settings saved.');
        }

        $data = $request->validate([
            'type_id' => ['required', 'integer', 'min:1'],
            'buy_percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
        ]);
        $ice = DB::table('eve_item_types as t')
            ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->where('t.type_id', $data['type_id'])
            ->where('t.published', true)
            ->where('g.group_name', 'Ice')
            ->where('t.type_name', 'like', 'Compressed %')
            ->first(['t.type_id', 't.type_name']);
        if (!$ice) {
            throw ValidationException::withMessages(['type_id' => ['Select a published compressed ice type from the Ice group.']]);
        }
        DB::table('buyback_system_status')->updateOrInsert(
            ['status_key' => 'ice_buy_percent_' . $ice->type_id],
            ['status_value' => (string) $data['buy_percent']]
        );
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => 'ice_buyback_rate.saved',
            'subject_type' => 'eve_ice_type',
            'subject_id' => $ice->type_id,
            'details' => json_encode(['ice' => $ice->type_name, 'buy_percent' => (float) $data['buy_percent']]),
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return back()->with('status', $ice->type_name . ' buyback rate saved.');
    }

    public function destroy(Request $request, int $categoryId): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        DB::table('appraisal_payout_rules')->where('category_id',$categoryId)->delete();
        return back()->with('status','Category rate removed. The default buyback rate will apply.');
    }
}
