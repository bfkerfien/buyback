<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportEveTypes extends Command
{
    protected $signature = 'buyback:import-types {file : Path to the EVE SDE type JSONL file}';
    protected $description = 'Import published EVE item types from CCP Static Data JSONL.';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (!is_file($path) || !is_readable($path)) {
            $this->error("Cannot read SDE file: {$path}");
            return self::FAILURE;
        }

        $batch = [];
        $imported = 0;
        $now = now();

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $lineNumber => $line) {
            try {
                $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                $this->warn('Skipping invalid JSON on line ' . ($lineNumber + 1));
                continue;
            }

            $id = $row['_key'] ?? $row['typeID'] ?? $row['type_id'] ?? null;
            $name = $row['name']['en'] ?? $row['name'] ?? $row['typeName'] ?? null;
            $published = $row['published'] ?? true;

            if (!$id || !is_numeric($id) || !is_string($name) || trim($name) === '' || !$published) {
                continue;
            }

            $batch[] = [
                'type_id' => (int) $id,
                'type_name' => trim($name),
                'published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= 1000) {
                DB::table('eve_item_types')->upsert($batch, ['type_id'], ['type_name', 'published', 'updated_at']);
                $imported += count($batch);
                $batch = [];
                $this->output->write('.');
            }
        }

        if ($batch) {
            DB::table('eve_item_types')->upsert($batch, ['type_id'], ['type_name', 'published', 'updated_at']);
            $imported += count($batch);
        }

        $this->newLine();
        $this->info("Imported or updated {$imported} published EVE item types.");
        return self::SUCCESS;
    }
}
