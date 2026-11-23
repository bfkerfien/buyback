<?php

namespace App\Http\Controllers;

use App\Services\Eve\ExcludedMissionOre;
use App\Services\Appraisal\InventoryParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class AppraisalComparisonController extends Controller
{
    public function manifests(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $compressedOres = DB::table('eve_item_types as t')
            ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->where('t.published', true)->where('g.category_name', 'Asteroid')
            ->where('t.type_name', 'like', 'Compressed %')
            ->orderBy('t.type_name')->get(['t.type_id', 't.type_name'])
            ->reject(fn ($type) => ExcludedMissionOre::isExcluded($type->type_name, (int) $type->type_id));

        $compressedIce = DB::table('eve_item_types as t')
            ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->where('t.published', true)->where('t.type_name', 'like', 'Compressed %')
            ->where(function ($query) {
                $query->where('g.group_name', 'like', '%ice%')
                    ->orWhere('g.category_name', 'like', '%ice%');
            })->orderBy('t.type_name')->get(['t.type_id', 't.type_name']);

        $pi = DB::table('eve_item_types as t')
            ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->where('t.published', true)->where('g.category_id', 43)
            ->orderBy('t.type_name')->get(['t.type_id', 't.type_name']);

        $exploration = DB::table('eve_item_types as t')
            ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->where('t.published', true)
            ->where(function ($query) {
                $query->where('g.group_name', 'like', '%salvage%')
                    ->orWhere('g.group_name', 'like', '%ancient relic%')
                    ->orWhere('g.group_name', 'like', '%data site%')
                    ->orWhere('g.group_name', 'like', '%relic site%');
            })->orderBy('t.type_name')->get(['t.type_id', 't.type_name']);

        $format = fn ($items) => $items->unique('type_id')
            ->map(fn ($item) => $item->type_name . "\t1000")->implode("\n");

        return response()->json([
            'ore' => $format($compressedOres->concat($compressedIce)),
            'pi' => $format($pi),
            'exploration' => $format($exploration),
            'counts' => [
                'ore' => $compressedOres->count() + $compressedIce->count(),
                'pi' => $pi->count(),
                'exploration' => $exploration->count(),
            ],
        ]);
    }

    public function compare(Request $request, InventoryParser $parser): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'holo_input' => ['required', 'string', 'max:50000'],
            'buyback_input' => ['required', 'string', 'max:50000'],
        ]);

        $sources = [];
        foreach (['Holo' => 'holo_input', 'EveBuyback' => 'buyback_input'] as $source => $field) {
            $sources[$source] = array_values(array_filter(
                $this->resolveInput($data[$field], $source, $field, $request, $parser),
                fn ($item) => !ExcludedMissionOre::isExcluded($item['name'])
            ));
        }

        $indexed = [];
        foreach ($sources as $source => $items) {
            $indexed[$source] = collect($items)->keyBy(fn ($item) => $this->key($item['name']));
        }
        $names = collect($indexed)->flatMap(fn ($items) => $items->keys())->unique()->sort()->values();

        $rows = $names->map(function ($key) use ($indexed) {
            $holo = $indexed['Holo']->get($key);
            $buyback = $indexed['EveBuyback']->get($key);
            $quantities = [
                'holo' => (int) ($holo['quantity'] ?? 0),
                'buyback' => (int) ($buyback['quantity'] ?? 0),
            ];
            $holoPresent = $quantities['holo'] > 0;
            $buybackPresent = $quantities['buyback'] > 0;
            $sameQuantity = $holoPresent && $buybackPresent && $quantities['holo'] === $quantities['buyback'];

            // Holo's fetched share page exposes inventory, not a verified per-item
            // payout in the HTML available to this server. Never infer a price from
            // arbitrary table columns (volume, batch size, etc.).
            $buybackPayout = isset($buyback['unit_payout']) && (float) $buyback['unit_payout'] > 0
                ? (float) $buyback['unit_payout'] : null;
            return [
                'name' => $holo['name'] ?? $buyback['name'],
                'holo_quantity' => $quantities['holo'],
                'buyback_quantity' => $quantities['buyback'],
                'holo_unit_price' => null,
                'buyback_unit_payout' => $buybackPayout,
                'quantity_status' => !$holoPresent ? 'missing_from_holo'
                    : (!$buybackPresent ? 'missing_from_buyback'
                    : ($sameQuantity ? 'same_quantity' : 'quantity_mismatch')),
                'price_status' => 'not_comparable_holo_price_unavailable',
                'status' => !$holoPresent || !$buybackPresent ? 'missing_source'
                    : ($sameQuantity ? 'same_quantity' : 'quantity_mismatch'),
            ];
        })->values();

        return response()->json([
            'holo_items' => count($sources['Holo']),
            'buyback_items' => count($sources['EveBuyback']),
            'same_quantity' => $rows->where('status', 'same_quantity')->count(),
            'differences' => $rows->where('status', '!=', 'same_quantity')->count(),
            'rows' => $rows,
        ]);
    }

    private function resolveInput(string $input, string $source, string $field, Request $request, InventoryParser $parser): array
    {
        $input = trim($input);
        if (filter_var($input, FILTER_VALIDATE_URL)) {
            $parts = parse_url($input);
            $host = strtolower((string) ($parts['host'] ?? ''));
            $path = (string) ($parts['path'] ?? '');

            if ($source === 'EveBuyback') {
                $allowedHost = strtolower((string) (parse_url(config('app.url'), PHP_URL_HOST) ?: $request->getHost()));
                abort_unless($host === $allowedHost && !isset($parts['user']) && !isset($parts['pass']) && (!isset($parts['port']) || (int) $parts['port'] === 443), 422, 'The EveBuyback link must point to this EveBuyback site.');
                if (!preg_match('~/q/([A-Za-z0-9]+)~', $path, $match)) {
                    throw ValidationException::withMessages([$field => 'Use an EveBuyback appraisal link in the form /q/{quote-token}.']);
                }
                $quote = DB::table('appraisal_quotes')->where('public_token', $match[1])->first();
                if (!$quote) throw ValidationException::withMessages([$field => 'That EveBuyback appraisal link was not found.']);
                return DB::table('appraisal_quote_items')->where('appraisal_quote_id', $quote->id)
                    ->orderBy('type_name')->get(['type_name', 'quantity', 'unit_price', 'source_data'])
                    ->map(function ($row) {
                        $source = json_decode($row->source_data ?? '{}', true) ?: [];
                        $payout = isset($source['payout_value']) ? (float) $source['payout_value'] : null;
                        return [
                            'name' => $row->type_name,
                            'quantity' => (int) $row->quantity,
                            'unit_price' => (float) $row->unit_price,
                            'unit_payout' => $payout !== null && (int) $row->quantity > 0 ? $payout / (int) $row->quantity : null,
                        ];
                    })->all();
            }

            abort_unless($source === 'Holo', 422, 'Only Holo share links and EveBuyback appraisal links are supported.');
            $allowedHosts = ['evebb.eu', 'www.evebb.eu'];
            abort_unless(in_array($host, $allowedHosts, true) && !isset($parts['user']) && !isset($parts['pass']) && (!isset($parts['port']) || (int) $parts['port'] === 443), 422, $source.' appraisal links must use the official '.$source.' host.');
            abort_unless(($parts['scheme'] ?? '') === 'https', 422, $source.' appraisal links must use HTTPS.');

            if (!preg_match('~^/~', $path)) {
                throw ValidationException::withMessages([$field => 'That does not look like a supported '.$source.' appraisal share link.']);
            }

            try {
                $response = Http::withOptions(['allow_redirects' => false])
                    ->withHeaders(['User-Agent' => 'EveBuyback Appraisal Comparator'])
                    ->accept('text/html')
                    ->timeout(20)
                    ->get($input);
            } catch (\Throwable $e) {
                throw ValidationException::withMessages([$field => 'Could not load that '.$source.' link. Paste its copied item list instead.']);
            }

            if (!$response->successful()) {
                throw ValidationException::withMessages([$field => $source.' did not return that appraisal link. Paste its copied item list instead.']);
            }

            $items = $this->parseHtml($response->body(), $source, $parser);
            if (!$items) {
                throw ValidationException::withMessages([$field => 'That '.$source.' page did not expose a readable item list. Paste the copied item list into this block instead.']);
            }
            return $items;
        }

        $items = $parser->parse($input);
        if (!$items) {
            throw ValidationException::withMessages([$field => 'No item names and quantities could be parsed. Paste the copied item list or a supported appraisal link.']);
        }
        return $items;
    }

    private function parseHtml(string $html, string $source, InventoryParser $parser): array
    {
        $items = [];
        if (preg_match_all('/<tr\\b[^>]*>(.*?)<\\/tr>/is', $html, $matches)) {
            foreach ($matches[1] as $row) {
                if (!preg_match_all('/<t[dh]\\b[^>]*>(.*?)<\\/t[dh]>/is', $row, $cells) || count($cells[1]) < 2) continue;
                $values = array_map(fn ($cell) => trim(html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML5, 'UTF-8')), $cells[1]);
                $name = $values[0] ?? '';
                $quantityText = $values[1] ?? '';
                if ($name === '' || !preg_match('/^[0-9][0-9,]*$/', $quantityText)) continue;
                if (preg_match('/^(item|type|name|quantity|total|subtotal)$/i', $name)) continue;
                $quantity = (int) str_replace(',', '', $quantityText);
                $items[] = ['name' => $name, 'quantity' => $quantity];
            }
        }

        if (!$items) {
            $plain = html_entity_decode(strip_tags(preg_replace('/<(script|style)\\b[^>]*>.*?<\\/\\1>/is', ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            foreach (preg_split('/\\r\\n|\\n|\\r/', $plain) ?: [] as $line) {
                $line = trim($line);
                if (preg_match('/^(.+?)\\s+[x×]?\\s*([0-9][0-9,]*)$/', $line, $match)) {
                    $name = trim($match[1]);
                    if ($name !== '' && !preg_match('/^(item|type|name|quantity|total|subtotal)$/i', $name)) {
                        $items[] = ['name' => $name, 'quantity' => (int) str_replace(',', '', $match[2])];
                    }
                }
            }
        }

        $merged = [];
        foreach ($items as $item) {
            if ($item['quantity'] < 1) continue;
            $key = $this->key($item['name']);
            if (!isset($merged[$key])) $merged[$key] = ['name' => $item['name'], 'quantity' => 0, 'unit_price' => null, 'unit_payout' => null];
            $merged[$key]['quantity'] += $item['quantity'];
        }
        return array_values($merged);
    }

    private function key(string $name): string
    {
        return mb_strtolower(preg_replace('/\\s+/', ' ', trim($name)));
    }
}
