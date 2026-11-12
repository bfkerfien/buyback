<?php

namespace App\Services\Appraisal;

final class InventoryParser
{
    /**
     * Accepts common EVE inventory exports:
     * Item Name<TAB>Quantity, Item Name,Quantity, and Quantity x Item Name.
     *
     * @return array<int, array{name:string, quantity:int}>
     */
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

            if (preg_match('/^([0-9][0-9,]*)\\s*[x×]\\s*(.+)$/i', $line, $m)) {
                $quantity = (int) str_replace(',', '', $m[1]);
                $name = trim($m[2]);
            } elseif (str_contains($line, "\t")) {
                $parts = array_map('trim', explode("\t", $line));
                if (count($parts) >= 2) {
                    if (is_numeric($parts[0])) {
                        $quantity = (int) $parts[0];
                        $name = $parts[1];
                    } else {
                        $name = $parts[0];
                        $quantity = (int) preg_replace('/[^0-9]/', '', $parts[1]);
                    }
                }
            } elseif (str_contains($line, ',')) {
                $parts = array_map('trim', str_getcsv($line));
                if (count($parts) >= 2) {
                    if (is_numeric($parts[0])) {
                        $quantity = (int) $parts[0];
                        $name = $parts[1];
                    } else {
                        $name = $parts[0];
                        $quantity = (int) preg_replace('/[^0-9]/', '', $parts[1]);
                    }
                }
            }

            if ($name === null && preg_match('/^([0-9][0-9,]*)\s*[x×]\s*(.+)$/i', $line, $m)) {
                $quantity = (int) str_replace(',', '', $m[1]);
                $name = trim($m[2]);
            }

            if ($name === null && preg_match('/^(.+?)\s+[x×]\s*([0-9][0-9,]*)$/i', $line, $m)) {
                $name = trim($m[1]);
                $quantity = (int) str_replace(',', '', $m[2]);
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
