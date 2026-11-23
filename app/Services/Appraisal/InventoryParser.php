<?php

namespace App\Services\Appraisal;

final class InventoryParser
{
    /**
     * Parse MarketBaiters rendered appraisal table rows. The share page is a
     * Next.js client page, but its rendered HTML contains a normal table with
     * item name, quantity, volume, category, and payout columns.
     *
     * @return array<int, array{name:string, quantity:int, unit_price:?float, unit_payout:?float}>
     */
    public function parseMarketBaitersHtml(string $html): array
    {
        $items = [];

        if (!preg_match_all('/<tr\\b[^>]*>(.*?)<\\/tr>/is', $html, $rows)) {
            return [];
        }

        foreach ($rows[1] as $row) {
            if (!preg_match_all('/<td\\b[^>]*>(.*?)<\\/td>/is', $row, $cells) || count($cells[1]) < 3) {
                continue;
            }

            $firstCell = $cells[1][0];

            // The item title is deliberately selected from its title span;
            // the same cell also contains the Refine button and yield text.
            if (!preg_match('/<span\\b(?=[^>]*\\bfont-medium\\b)(?=[^>]*\\btext-bright\\b)[^>]*>(.*?)<\\/span>/is', $firstCell, $title)) {
                continue;
            }

            $name = trim(html_entity_decode(strip_tags($title[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $quantityText = trim(html_entity_decode(strip_tags($cells[1][1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($name === '' || !preg_match('/^[0-9][0-9,]*$/', $quantityText)) {
                continue;
            }

            $quantity = (int) str_replace(',', '', $quantityText);
            if ($quantity < 1 || $quantity > 1000000000) {
                continue;
            }

            $payoutText = trim(html_entity_decode(strip_tags($cells[1][count($cells[1]) - 1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $totalPayout = $this->parseMarketBaitersAmount($payoutText);
            $key = mb_strtolower(preg_replace('/\\s+/', ' ', $name));

            if (!isset($items[$key])) {
                $items[$key] = [
                    'name' => $name,
                    'quantity' => 0,
                    'total_payout' => 0.0,
                    'has_payout' => false,
                ];
            }

            $items[$key]['quantity'] += $quantity;
            if ($totalPayout !== null) {
                $items[$key]['total_payout'] += $totalPayout;
                $items[$key]['has_payout'] = true;
            }
        }

        return array_values(array_map(static function (array $item): array {
            $unitPayout = $item['has_payout'] && $item['quantity'] > 0
                ? $item['total_payout'] / $item['quantity']
                : null;

            return [
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'unit_price' => $unitPayout,
                'unit_payout' => $unitPayout,
            ];
        }, $items));
    }

    private function parseMarketBaitersAmount(string $value): ?float
    {
        $value = strtoupper(str_replace([',', ' ', 'ISK'], '', trim($value)));
        if (!preg_match('/^([0-9]+(?:\\.[0-9]+)?)([KMB])?$/', $value, $match)) {
            return null;
        }

        $multiplier = match ($match[2] ?? '') {
            'K' => 1000,
            'M' => 1000000,
            'B' => 1000000000,
            default => 1,
        };

        return (float) $match[1] * $multiplier;
    }

    /**
     * Parse the JSON payload returned by MarketBaiters' public appraisal API.
     * Only top-level appraisal rows are imported. Reprocessing sub_items are
     * breakdowns of their parent ore and must not be counted as extra inventory.
     *
     * @return array<int, array{name:string, quantity:int, unit_price:?float, unit_payout:?float}>
     */
    public function parseMarketBaitersJson(array $payload): array
    {
        $rows = $payload['items'] ?? null;
        if (!is_array($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $quantity = filter_var($row['quantity'] ?? null, FILTER_VALIDATE_INT);
            if ($name === '' || $quantity === false || $quantity < 1 || $quantity > 1000000000) {
                continue;
            }

            $payout = $row['payout'] ?? null;
            $unitPayout = is_numeric($payout) && (float) $payout >= 0
                ? (float) $payout / $quantity
                : null;

            $key = mb_strtolower(preg_replace('/\\s+/', ' ', $name));
            if (!isset($items[$key])) {
                $items[$key] = [
                    'name' => $name,
                    'quantity' => 0,
                    'total_payout' => 0.0,
                    'has_payout' => false,
                ];
            }

            $items[$key]['quantity'] += $quantity;
            if ($unitPayout !== null) {
                $items[$key]['total_payout'] += $unitPayout * $quantity;
                $items[$key]['has_payout'] = true;
            }
        }

        return array_values(array_map(static function (array $item): array {
            $unitPayout = $item['has_payout'] && $item['quantity'] > 0
                ? $item['total_payout'] / $item['quantity']
                : null;

            return [
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'unit_price' => $unitPayout,
                'unit_payout' => $unitPayout,
            ];
        }, $items));
    }

    /**
     * Parse MarketBaiters' copied appraisal cards. Each card has an item-name
     * heading, a "Refine" action, a batches/yield line, and a result row such
     * as "1,000 160 ore refined 2.87M". Only card headings are item names;
     * action labels and refinement-result columns must never become items.
     *
     * @return array<int, array{name:string, quantity:int, unit_price:?float, unit_payout:?float}>
     */
    public function parseMarketBaiters(string $input): array
    {
        $lines = preg_split('/\\r\\n|\\n|\\r/', str_replace("\r", '', $input)) ?: [];
        $items = [];

        for ($i = 0, $count = count($lines); $i < $count - 1; $i++) {
            $name = trim($lines[$i]);
            if ($name === '' || strcasecmp(trim($lines[$i + 1]), 'Refine') !== 0) {
                continue;
            }

            $quantity = null;
            $totalValue = null;
            for ($j = $i + 2; $j < min($count, $i + 6); $j++) {
                $row = trim($lines[$j]);
                if (preg_match('/^([0-9][0-9,]*)\\s+.*\\b(?:moon\\s+ore|ore|ice)\\s+refined\\b/i', $row, $match)) {
                    $quantity = (int) str_replace(',', '', $match[1]);
                    if (preg_match('/([0-9][0-9,]*(?:\\.[0-9]+)?\\s*[KMB])\\s*(?:ISK)?\\s*$/i', $row, $money)) {
                        $token = strtoupper(preg_replace('/\\s+/', '', $money[1]));
                        $suffix = substr($token, -1);
                        $amount = (float) substr($token, 0, -1);
                        $totalValue = $amount * match ($suffix) {
                            'K' => 1000,
                            'M' => 1000000,
                            'B' => 1000000000,
                        };
                    }
                    break;
                }
            }

            if ($quantity === null || $quantity < 1 || $quantity > 1000000000) {
                continue;
            }

            $key = mb_strtolower(preg_replace('/\\s+/', ' ', $name));
            if (!isset($items[$key])) {
                $items[$key] = ['name' => $name, 'quantity' => 0, 'total_value' => 0.0, 'has_value' => false];
            }
            $items[$key]['quantity'] += $quantity;
            if ($totalValue !== null) {
                $items[$key]['total_value'] += $totalValue;
                $items[$key]['has_value'] = true;
            }
        }

        return array_values(array_map(static function (array $item): array {
            $unitPrice = $item['has_value'] && $item['quantity'] > 0
                ? $item['total_value'] / $item['quantity']
                : null;

            return [
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'unit_price' => $unitPrice,
                'unit_payout' => $unitPrice,
            ];
        }, $items));
    }

    public function parse(string $input): array
    {
        $items = [];

        foreach (preg_split('/\r\n|\n|\r/', trim($input)) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || preg_match('/^(item|type name|name)\s*[,\t]/i', $line)) {
                continue;
            }

            $name = null;
            $quantity = null;

            if (preg_match('/^([0-9][0-9,]*)\s*[x×]\s*(.+)$/i', $line, $m)) {
                $quantity = (int) str_replace(',', '', $m[1]);
                $name = trim($m[2]);
            } elseif (preg_match('/^([0-9][0-9,]*)\s+(.+)$/', $line, $m)) {
                $quantity = (int) str_replace(',', '', $m[1]);
                $name = trim($m[2]);
            } elseif (preg_match('/^(.+?)\s+[x×]\s*([0-9][0-9,]*)$/i', $line, $m)) {
                $name = trim($m[1]);
                $quantity = (int) str_replace(',', '', $m[2]);
            } elseif (preg_match('/^(.+?)\s+([0-9][0-9,]*)$/', $line, $m)) {
                // Supports the exact pasted format: "Compressed Mercoxit 100000".
                $name = trim($m[1]);
                $quantity = (int) str_replace(',', '', $m[2]);
            } elseif (str_contains($line, "\t")) {
                $parts = array_map('trim', explode("\t", $line));
                if (count($parts) >= 2) {
                    if (is_numeric(str_replace(',', '', $parts[0]))) {
                        $quantity = (int) str_replace(',', '', $parts[0]);
                        $name = $parts[1];
                    } else {
                        $name = $parts[0];
                        $quantity = (int) preg_replace('/[^0-9]/', '', $parts[1]);
                    }
                }
            } elseif (str_contains($line, ',')) {
                $parts = array_map('trim', str_getcsv($line));
                if (count($parts) >= 2) {
                    if (is_numeric(str_replace(',', '', $parts[0]))) {
                        $quantity = (int) str_replace(',', '', $parts[0]);
                        $name = $parts[1];
                    } else {
                        $name = $parts[0];
                        $quantity = (int) preg_replace('/[^0-9]/', '', $parts[1]);
                    }
                }
            }

            if ($name === null) {
                $name = $line;
                $quantity = 1;
            }

            $name = trim((string) $name);
            if ($name === '' || $quantity === null || $quantity < 1 || $quantity > 1000000000) {
                continue;
            }

            $key = mb_strtolower($name);
            if (!isset($items[$key])) {
                $items[$key] = ['name' => $name, 'quantity' => 0];
            }
            $items[$key]['quantity'] += $quantity;
        }

        return array_values($items);
    }
}
