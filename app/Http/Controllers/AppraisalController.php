<?php

namespace App\Http\Controllers;

use App\Services\Appraisal\AppraisalCalculator;
use App\Services\Appraisal\InventoryParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AppraisalController extends Controller
{
    public function store(Request $request, InventoryParser $parser, AppraisalCalculator $calculator): RedirectResponse
    {
        $validated = $request->validate([
            'inventory' => ['required', 'string', 'max:100000'],
            'origin_system' => ['nullable', 'string', 'max:100'],
        ]);

        $items = $parser->parse($validated['inventory']);
        if (!$items) {
            return back()->withErrors(['inventory' => 'No inventory lines were recognized. Paste item names and quantities.'])->withInput();
        }

        $result = $calculator->calculate($items);
        $token = Str::random(48);

        DB::transaction(function () use ($result, $validated, $token, $request) {
            $quoteId = DB::table('appraisal_quotes')->insertGetId([
                'public_token' => $token,
                'eve_character_id' => $request->user()?->characters()->value('id'),
                'created_by' => $request->user()?->id,
                'origin_system' => $validated['origin_system'] ?? null,
                'status' => 'priced',
                'price_source' => 'market_price_snapshots',
                'priced_at' => now(),
                'calculation_version' => 1,
                'gross_value' => $result['gross_value'],
                'fees' => $result['fees'],
                'payout_value' => $result['payout_value'],
                'pricing_snapshot' => json_encode([
                    'price_side' => $result['price_side'],
                    'payout_percent' => $result['payout_percent'],
                    'region_id' => $result['region_id'],
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($result['lines'] as $line) {
                DB::table('appraisal_quote_items')->insert([
                    'appraisal_quote_id' => $quoteId,
                    'type_id' => $line['type_id'],
                    'type_name' => $line['type_name'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_value' => $line['line_value'],
                    'source_data' => json_encode([
                        'source' => $line['source'],
                        'observed_at' => $line['observed_at'],\n                        'payout_percent' => $line['payout_percent'],\n                        'payout_value' => $line['payout_value'],\n                        'category_id' => $line['category_id'],\n                        'category_name' => $line['category_name'],\n                        'rate_source' => $line['rate_source'],\n                        'price_side' => $result['price_side'],
                        'payout_percent' => $line['payout_percent'],
                        'payout_value' => $line['payout_value'],
                    ], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return redirect()->route('appraisals.show', $token);
    }

    public function show(string $token): View
    {
        $quote = DB::table('appraisal_quotes')->where('public_token', $token)->first();
        abort_unless($quote, 404);

        $items = DB::table('appraisal_quote_items')
            ->where('appraisal_quote_id', $quote->id)
            ->orderBy('type_name')
            ->get();

        return view('appraisals.show', compact('quote', 'items'));
    }
}
