<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayoutRuleController extends Controller
{
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

    public function storeOreShippingCost(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'cost_per_m3' => ['required', 'numeric', 'min:0', 'max:1000000'],
        ]);

        DB::table('buyback_system_status')->updateOrInsert(
            ['status_key' => 'ore_shipping_cost_per_m3'],
            ['status_value' => (string) $data['cost_per_m3']]
        );

        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => 'ore_shipping_cost.saved',
            'subject_type' => 'ore_shipping',
            'subject_id' => null,
            'details' => json_encode(['cost_per_m3' => (float) $data['cost_per_m3']]),
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Ore shipping cost saved.');
    }

    public function storeGasPricing(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'uncompressed_percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'compressed_percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'price_depth' => ['required', 'integer', 'min:1', 'max:2000000000'],
        ]);

        $settings = [
            'gas_uncompressed_percent' => (string) $data['uncompressed_percent'],
            'gas_compressed_percent' => (string) $data['compressed_percent'],
            'gas_buy_depth' => (string) $data['price_depth'],
        ];
        foreach ($settings as $key => $value) {
            DB::table('buyback_system_status')->updateOrInsert(
                ['status_key' => $key],
                ['status_value' => $value]
            );
        }

        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => 'gas_buyback_settings.saved',
            'subject_type' => 'compressed_gas_market',
            'subject_id' => null,
            'details' => json_encode($data),
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Gas buyback percentages and compressed-gas Jita unit depth saved.');
    }

    public function storeIcePricing(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'type_id' => ['required', 'integer', 'min:1'],
            'buy_percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'price_depth' => ['required', 'integer', 'min:1', 'max:2000000000'],
        ]);

        $product = DB::table('eve_item_types as t')
            ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->where('t.type_id', $data['type_id'])
            ->where('t.published', true)
            ->where(function ($query) {
                $query->where(function ($ice) {
                    $ice->whereIn('g.group_name', ['Ice', 'Compressed Ice'])
                        ->where('t.type_name', 'like', 'Compressed %');
                })->orWhereIn('t.type_name', [
                    'Nitrogen Isotopes',
                    'Heavy Water',
                    'Strontium Clathrates',
                    'Liquid Ozone',
                ]);
            })
            ->first(['t.type_id', 't.type_name']);

        if (!$product) {
            throw ValidationException::withMessages([
                'type_id' => ['Select a published compressed ice or supported ice product.'],
            ]);
        }

        $settings = [
            'ice_buy_percent_' . $product->type_id => (string) $data['buy_percent'],
            'ice_buy_depth_' . $product->type_id => (string) $data['price_depth'],
        ];

        foreach ($settings as $key => $value) {
            DB::table('buyback_system_status')->updateOrInsert(
                ['status_key' => $key],
                ['status_value' => $value]
            );
        }

        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => 'ice_product_pricing.saved',
            'subject_type' => 'eve_ice_product',
            'subject_id' => $product->type_id,
            'details' => json_encode([
                'product' => $product->type_name,
                'buy_percent' => (float) $data['buy_percent'],
                'price_depth' => (int) $data['price_depth'],
            ]),
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with(
            'status',
            $product->type_name . ' buy percentage and Jita depth saved.'
        );
    }


}
