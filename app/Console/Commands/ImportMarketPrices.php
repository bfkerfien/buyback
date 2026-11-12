<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportMarketPrices extends Command
{
    protected $signature = 'buyback:import-prices {file : CSV with type_id,buy_price,sell_price columns} {--region=10000002}';
    protected $description = 'Import timestamped market price observations from CSV.';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (!is_file($path) || !is_readable($path)) {
            $this->error("Cannot read price CSV: {$path}");
            return self::FAILURE;
        }

        $handle = fopen($path, 'rb');
        if (!$handle) {
            $this->error('Unable to open price CSV.');
            return self::FAILURE;
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            $this->error('CSV is empty.');
            return self::FAILURE;
        }

        $header = array_map(fn ($value) => strtolower(trim((string) $value)), $header);
        $required = ['type_id', 'buy_price', 'sell_price'];
        if (array_diff($required, $header)) {
            fclose($handle);
            $this->error('CSV must include type_id,buy_price,sell_price columns.');
            return self::FAILURE;
        }

        $indexes = array_flip($header);
        $region = (string) $this->option('region');
        $batch = [];
        $count = 0;
        $now = now();

        while (($row = fgetcsv($handle)) !== false) {
            $typeId = (int) ($row[$indexes['type_id']] ?? 0);
            if ($typeId < 1) {
                continue;
            }

            $buy = $row[$indexes['buy_price']] ?? null;
            $sell = $row[$indexes['sell_price']] ?? null;
            $observed = isset($indexes['observed_at']) && !empty($row[$indexes['observed_at']])
                ? $row[$indexes['observed_at']]
                : $now;
            try {
                $observedAt = Carbon::parse($observed);
            } catch (Throwable) {
                $this->warn("Skipping invalid timestamp for type {$typeId}.");
                continue;
            }

            $batch[] = [
                'type_id' => $typeId,
                'region_id' => $region,
                'source' => isset($indexes['source']) ? (string) ($row[$indexes['source']] ?? 'csv-import') : 'csv-import',
                'buy_price' => is_numeric($buy) ? (float) $buy : null,
                'sell_price' => is_numeric($sell) ? (float) $sell : null,
                'observed_at' => $observedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= 1000) {
                DB::table('market_price_snapshots')->insert($batch);
                $count += count($batch);
                $batch = [];
            }
        }

        fclose($handle);

        if ($batch) {
            DB::table('market_price_snapshots')->insert($batch);
            $count += count($batch);
        }

        $this->info("Imported {$count} market price observations for region {$region}.");
        return self::SUCCESS;
    }
}
