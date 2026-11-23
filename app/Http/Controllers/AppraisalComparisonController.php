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
        ]);

        $holo = array_values(array_filter(
            $this->resolveInput($data['holo_input'], 'Holo', $request, $parser),
            fn ($item) => !ExcludedMissionOre::isExcluded($item['name'])
        ));
        $buyback = array_values(array_filter(
            $this->resolveInput($data['buyback_input'], 'EveBuyback', $request, $parser),
            fn ($item) => !ExcludedMissionOre::isExcluded($item['name'])
        ));

        $left = collect($holo)->keyBy(fn ($item) => $this->key($item['name']));
        $right = collect($buyback)->keyBy(fn ($item) => $this->key($item['name']));
        $names = $left->keys()->merge($right->keys())->unique()->sort()->values();

        $rows = $names->map(function ($key) use ($left, $right) {
            $a = $left->get($key);
            $b = $right->get($key);
            $aq = (int) ($a['quantity'] ?? 0);
            $bq = (int) ($b['quantity'] ?? 0);
            return [
                'name' => $a['name'] ?? $b['name'],
                'holo_quantity' => $aq,
                'buyback_quantity' => $bq,
                'difference' => $bq - $aq,
                'status' => !$a ? 'missing_marketbaiters' : (!$b ? 'missing_buyback' : ($aq === $bq ? 'match' : 'quantity_mismatch')),
            ];
        })->values();

        return response()->json([
            'holo_items' => count($holo),
            'buyback_items' => count($buyback),
            'matched' => $rows->where('status', 'match')->count(),
            'differences' => $rows->where('status', '!=', 'match')->count(),
            'rows' => $rows,
        ]);
    }

    private function resolveInput(string $input, string $source, Request $request, InventoryParser $parser): array
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
                    throw ValidationException::withMessages(['buyback_input' => 'Use an EveBuyback appraisal link in the form /q/{quote-token}.']);
                }
                $quote = DB::table('appraisal_quotes')->where('public_token', $match[1])->first();
                if (!$quote) throw ValidationException::withMessages(['buyback_input' => 'That EveBuyback appraisal link was not found.']);
                return DB::table('appraisal_quote_items')->where('appraisal_quote_id', $quote->id)
                    ->orderBy('type_name')->get(['type_name', 'quantity'])
                    ->map(fn ($row) => ['name' => $row->type_name, 'quantity' => (int) $row->quantity])->all();
            }

            if ($source === 'Holo') {
                abort_unless(in_array($host, ['evebb.eu', 'www.evebb.eu'], true) && !isset($parts['user']) && !isset($parts['pass']) && (!isset($parts['port']) || (int) $parts['port'] === 443), 422, 'Holo links must use https://evebb.eu/.');
                abort_unless(($parts['scheme'] ?? '') === 'https', 422, 'Holo appraisal links must use HTTPS.');
                try {
                    $response = Http::withOptions(['allow_redirects' => false])
                        ->withHeaders(['User-Agent' => 'EveBuyback Holo Comparator'])
                        ->accept('text/html')
                        ->timeout(15)
                        ->get($input);
                } catch (\\Throwable $e) {
                    throw ValidationException::withMessages(['holo_input' => 'Could not load that Holo page. Check the link and try again, or paste the copied appraisal item list.']);
                }
                if (!$response->successful()) {
                    throw ValidationException::withMessages(['holo_input' => 'Holo did not return that appraisal page. Check that the appraisal link is public, or paste its copied item list.']);
                }
                $items = $this->parseHtml($response->body(), $parser);
                if (!$items) {
                    throw ValidationException::withMessages(['holo_input' => 'The Holo page loaded but no item rows could be read. This link may require a different parser; paste the copied item list for now.']);
                }
                return $items;
            }

            throw ValidationException::withMessages(['buyback_input' => 'Paste an EveBuyback /q/ link or copied item list.']);
        }

        $items = $parser->parse($input);
        if (!$items) {
            $field = $source === 'Holo' ? 'holo_input' : 'buyback_input';
            throw ValidationException::withMessages([$field => 'No item names and quantities could be parsed. Paste the copied appraisal item list or a supported appraisal link.']);
        }

        return $items;
    }

    private function parseHtml(string $html, InventoryParser $parser): array
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

        if ($items) return $this->mergeItems($items);
        // Only parse explicit name/quantity lines from page text. Passing an entire
        // generic app page to InventoryParser would treat navigation labels as qty=1.
        $plain = html_entity_decode(strip_tags(preg_replace('/<(script|style)\\b[^>]*>.*?<\\/\\1>/is', " ", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = preg_split('/\\r\\n|\\n|\\r/', $plain) ?: [];
        $structured = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/[ \\t]+/', ' ', $line));
            if ($line === '') continue;
            if (str_contains($line, "\t") || str_contains($line, ',' ) || preg_match('/^(?:[0-9][0-9,]*\\s*(?:[x×]\\s*)?.+|.+\\s+[x×]?\\s*[0-9][0-9,]*)$/i', $line)) {
                $structured[] = $line;
            }
        }
        return $structured ? $parser->parse(implode("\n", $structured)) : [];
    }

    private function mergeItems(array $items): array
    {
        $merged = [];
        foreach ($items as $item) {
            $key = $this->key($item['name']);
            if (!isset($merged[$key])) $merged[$key] = ['name' => $item['name'], 'quantity' => 0];
            $merged[$key]['quantity'] += (int) $item['quantity'];
        }
        return array_values($merged);
    }

    private function key(string $name): string
    {
        return mb_strtolower(preg_replace('/\\s+/', ' ', trim($name)));
    }
}
