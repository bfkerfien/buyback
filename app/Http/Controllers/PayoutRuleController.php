<?php

namespace App\\Http\\Controllers;

use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Validation\\ValidationException;

class PayoutRuleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'payout_percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
        ]);

        $item = DB::table('eve_item_types')->where('published', true)
            ->whereRaw('LOWER(type_name) = ?', [mb_strtolower(trim($data['item_name']))])
            ->first(['type_id', 'type_name']);

        if (!$item) {
            throw ValidationException::withMessages([
                'item_name' => ['That exact published EVE item name was not found. Check the spelling and try again.'],
            ]);
        }

        DB::table('appraisal_payout_rules')->updateOrInsert(
            ['type_id' => $item->type_id],
            ['payout_percent' => $data['payout_percent'], 'updated_at' => now(), 'created_at' => now()]
        );

        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => 'appraisal_payout_rule.saved',
            'subject_type' => 'eve_item_type',
            'subject_id' => $item->type_id,
            'details' => json_encode(['item' => $item->type_name, 'payout_percent' => (float) $data['payout_percent']]),
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Buyback rate saved for ' . $item->type_name . '.');
    }

    public function destroy(Request $request, int $typeId): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        DB::table('appraisal_payout_rules')->where('type_id', $typeId)->delete();
        return back()->with('status', 'Item rate removed. The default buyback rate will apply.');
    }
}
