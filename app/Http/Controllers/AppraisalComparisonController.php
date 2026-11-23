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

            $holoPayout = isset($holo['unit_payout']) && (float) $holo['unit_payout'] > 0
                ? (float) $holo['unit_payout'] : null;
            $buybackPayout = isset($buyback['unit_payout']) && (float) $buyback['unit_payout'] > 0
                ? (float) $buyback['unit_payout'] : null;
            $comparable = $holoPayout !== null && $buybackPayout !== null;
            $difference = $comparable ? $buybackPayout - $holoPayout : null;
            $differencePercent = $comparable && $holoPayout > 0
                ? ($difference / $holoPayout) * 100 : null;
            $priceStatus = !$comparable ? 'not_comparable'
                : (abs($difference) < 0.005 ? 'equal'
                : ($difference > 0 ? 'buyback_higher' : 'holo_higher'));
            return [
                'name' => $holo['name'] ?? $buyback['name'],
                'holo_quantity' => $quantities['holo'],
                'buyback_quantity' => $quantities['buyback'],
                'holo_total_payout' => isset($holo['total_payout']) ? (float) $holo['total_payout'] : null,
                'holo_unit_payout' => $holoPayout,
                'holo_payout_basis' => $holo['payout_basis'] ?? null,
                'buyback_total_payout' => isset($buyback['total_payout']) ? (float) $buyback['total_payout'] : null,
                'buyback_unit_payout' => $buybackPayout,
                'payout_difference_per_unit' => $difference,
                'payout_difference_percent' => $differencePercent,
                'price_status' => $priceStatus,
                'status' => $priceStatus,
            ];
        })->values();

        return response()->json([
            'holo_items' => count($sources['Holo']),
            'buyback_items' => count($sources['EveBuyback']),
            'comparable_items' => $rows->whereIn('price_status', ['equal', 'buyback_higher', 'holo_higher'])->count(),
            'holo_higher' => $rows->where('price_status', 'holo_higher')->count(),
            'buyback_higher' => $rows->where('price_status', 'buyback_higher')->count(),
            'equal_payout' => $rows->where('price_status', 'equal')->count(),
            'not_comparable' => $rows->where('price_status', 'not_comparable')->count(),
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
                            'total_payout' => $payout,
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

        if ($source === 'Holo') {
            $holoItems = $this->parseHoloText($input);
            if ($holoItems) return $holoItems;
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
        if (preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $html, $matches)) {
            foreach ($matches[1] as $row) {
                if (!preg_match_all('/<t[dh]\b[^>]*>(.*?)<\/t[dh]>/is', $row, $cells) || count($cells[1]) < 3) continue;
                $values = array_map(fn ($cell) => trim(html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML5, 'UTF-8')), $cells[1]);
                $name = $values[0] ?? '';
                $quantityText = $values[1] ?? '';
                if ($name === '' || !preg_match('/^[0-9][0-9,]*$/', $quantityText)) continue;
                if (preg_match('/^(item|type|name|quantity|total|subtotal)$/i', $name)) continue;
                $quantity = (int) str_replace(',', '', $quantityText);
                if ($quantity < 1) continue;
                $valuation = implode(' ', array_slice($values, 2));
                $parsed = $this->parseHoloValuation($valuation, $quantity);
                if (!$parsed) continue;
                $items[] = array_merge(['name' => $name, 'quantity' => $quantity], $parsed);
            }
        }

        if (!$items) {
            $plain = html_entity_decode(strip_tags(preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return $this->parseHoloText($plain);
        }

        return $this->mergeHoloItems($items);
    }

    /**
     * Parse Holo's copied appraisal result:
     * item name, quantity, then total ISK and the pricing basis.
     * Some special items also expose a separate per-unit ISK amount.
     */
    private function parseHoloText(string $text): array
    {
        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\n|\r/', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: []),
            fn ($line) => $line !== ''
        ));
        $items = [];

        for ($i = 0; $i < count($lines) - 2; $i++) {
            $name = $lines[$i];
            $quantityText = $lines[$i + 1];
            if (!preg_match('/^[0-9][0-9,]*$/', $quantityText)) continue;
            if (preg_match('/^(item|type|name|quantity|total|subtotal)$/i', $name)) continue;

            $quantity = (int) str_replace(',', '', $quantityText);
            if ($quantity < 1) continue;
            $valuation = $this->parseHoloValuation($lines[$i + 2], $quantity);
            if (!$valuation) continue;

            $items[] = array_merge(['name' => $name, 'quantity' => $quantity], $valuation);
            $i += 2;
        }

        return $this->mergeHoloItems($items);
    }

    private function parseHoloValuation(string $text, int $quantity): ?array
    {
        if (!preg_match_all('/([0-9][0-9,]*(?:\.[0-9]+)?)\s*ISK/i', $text, $matches) || empty($matches[1])) {
            return null;
        }

        $amounts = array_map(fn ($amount) => (float) str_replace(',', '', $amount), $matches[1]);
        $total = $amounts[0];
        $unit = isset($amounts[1]) ? $amounts[1] : ($quantity > 0 ? $total / $quantity : null);
        if ($unit === null || $unit < 0) return null;

        $basis = trim(preg_replace('/[0-9][0-9,]*(?:\.[0-9]+)?\s*ISK/i', '', $text));
        return [
            'total_payout' => $total,
            'unit_payout' => $unit,
            'payout_basis' => $basis !== '' ? $basis : null,
        ];
    }

    private function mergeHoloItems(array $items): array
    {
        $merged = [];
        foreach ($items as $item) {
            $quantity = (int) ($item['quantity'] ?? 0);
            if ($quantity < 1) continue;
            $key = $this->key($item['name']);
            if (!isset($merged[$key])) {
                $merged[$key] = [
                    'name' => $item['name'],
                    'quantity' => 0,
                    'total_payout' => 0.0,
                    'unit_payout_total' => 0.0,
                    'unit_payout' => null,
                    'payout_basis' => $item['payout_basis'] ?? null,
                ];
            }
            $merged[$key]['quantity'] += $quantity;
            $merged[$key]['total_payout'] += (float) ($item['total_payout'] ?? 0);
            $merged[$key]['unit_payout_total'] += (float) ($item['unit_payout'] ?? 0) * $quantity;
            if (!empty($item['payout_basis'])) $merged[$key]['payout_basis'] = $item['payout_basis'];
        }

        foreach ($merged as &$item) {
            $item['unit_payout'] = $item['quantity'] > 0
                ? $item['unit_payout_total'] / $item['quantity'] : null;
            unset($item['unit_payout_total']);
        }
        unset($item);

        return array_values($merged);
    }

    private function key(string $name): string
    {
        return mb_strtolower(preg_replace('/\\s+/', ' ', trim($name)));
    }
}
