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
    public function compare(Request $request, InventoryParser $parser): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'holo_input' => ['required', 'string', 'max:50000'],
            'buyback_input' => ['required', 'string', 'max:50000'],
            'marketbaiters_input' => ['required', 'string', 'max:50000'],
        ]);

        $sources = [];
        foreach (['Holo' => 'holo_input', 'EveBuyback' => 'buyback_input', 'MarketBaiters' => 'marketbaiters_input'] as $source => $field) {
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
            $marketbaiters = $indexed['MarketBaiters']->get($key);
            $quantities = [
                'holo' => (int) ($holo['quantity'] ?? 0),
                'buyback' => (int) ($buyback['quantity'] ?? 0),
                'marketbaiters' => (int) ($marketbaiters['quantity'] ?? 0),
            ];
            $present = array_filter($quantities, fn ($quantity) => $quantity > 0);
            $allPresent = count($present) === 3;
            $sameQuantity = $allPresent && count(array_unique(array_values($quantities))) === 1;

            return [
                'name' => $holo['name'] ?? $buyback['name'] ?? $marketbaiters['name'],
                'holo_quantity' => $quantities['holo'],
                'buyback_quantity' => $quantities['buyback'],
                'marketbaiters_quantity' => $quantities['marketbaiters'],
                'status' => $sameQuantity ? 'match' : ($allPresent ? 'quantity_mismatch' : 'missing_source'),
            ];
        })->values();

        return response()->json([
            'holo_items' => count($sources['Holo']),
            'buyback_items' => count($sources['EveBuyback']),
            'marketbaiters_items' => count($sources['MarketBaiters']),
            'matched' => $rows->where('status', 'match')->count(),
            'differences' => $rows->where('status', '!=', 'match')->count(),
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
                    ->orderBy('type_name')->get(['type_name', 'quantity'])
                    ->map(fn ($row) => ['name' => $row->type_name, 'quantity' => (int) $row->quantity])->all();
            }

            $allowedHosts = $source === 'Holo'
                ? ['evebb.eu', 'www.evebb.eu']
                : ['marketbaiters.com', 'www.marketbaiters.com'];
            abort_unless(in_array($host, $allowedHosts, true) && !isset($parts['user']) && !isset($parts['pass']) && (!isset($parts['port']) || (int) $parts['port'] === 443), 422, $source.' appraisal links must use the official '.$source.' host.');
            abort_unless(($parts['scheme'] ?? '') === 'https', 422, $source.' appraisal links must use HTTPS.');

            $expectedPath = $source === 'MarketBaiters' ? '~/a/[A-Za-z0-9]+/?$~' : '~^/~';
            if (!preg_match($expectedPath, $path)) {
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

            $items = $this->parseHtml($response->body());
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

    private function parseHtml(string $html): array
    {
        $items = [];
        if (preg_match_all('/<tr\\b[^>]*>(.*?)<\\/tr>/is', $html, $matches)) {
            foreach ($matches[1] as $row) {
                if (!preg_match_all('/<t[dh]\\b[^>]*>(.*?)<\\/t[dh]>/is', $row, $cells) || count($cells[1]) < 2) continue;
                $name = trim(html_entity_decode(strip_tags($cells[1][0]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $quantityText = trim(html_entity_decode(strip_tags($cells[1][1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($name === '' || !preg_match('/^[0-9][0-9,]*$/', $quantityText)) continue;
                if (preg_match('/^(item|type|name|quantity|total|subtotal)$/i', $name)) continue;
                $items[] = ['name' => $name, 'quantity' => (int) str_replace(',', '', $quantityText)];
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
            if (!isset($merged[$key])) $merged[$key] = ['name' => $item['name'], 'quantity' => 0];
            $merged[$key]['quantity'] += $item['quantity'];
        }
        return array_values($merged);
    }

    private function key(string $name): string
    {
        return mb_strtolower(preg_replace('/\\s+/', ' ', trim($name)));
    }
}
