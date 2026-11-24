<?php

namespace App\\Console\\Commands;

use Illuminate\\Console\\Command;
use Illuminate\\Support\\Facades\\DB;
use Throwable;

class ImportEveMetaTypes extends Command
{
    protected $signature = 'buyback:import-meta-types {file : Path to the CCP SDE types.jsonl file}';
    protected $description = 'Import EVE item meta group IDs, including Tech II classification, from CCP SDE JSONL.';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (!is_file($path) || !is_readable($path)) {
            $this->error("Cannot read SDE file: {$path}");
            return self::FAILURE;
        }

        $handle = fopen($path, 'rb');
        if (!$handle) {
            $this->error("Cannot open SDE file: {$path}");
            return self::FAILURE;
        }

        $batch = [];
        $imported = 0;
        $lineNumber = 0;
        $now = now();

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            try {
                $raw = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                $this->warn("Skipping invalid JSON on line {$lineNumber}.");
                continue;
            }

            $value = $raw['_value'] ?? $raw;
            if (!is_array($value)) {
                continue;
            }

            $typeId = $raw['_key'] ?? $value['typeID'] ?? $value['type_id'] ?? null;
            $metaGroupId = $value['metaGroupID'] ?? $value['meta_group_id'] ?? null;
            $typeName = $value['name']['en'] ?? $value['typeName'] ?? null;

            if (!is_numeric($typeId) || !is_numeric($metaGroupId) || !is_string($typeName) || trim($typeName) === '') {
                continue;
            }

            $batch[] = [
                'type_id' => (int) $typeId,
                'type_name' => trim($typeName),
                'published' => (bool) ($value['published'] ?? true),
                'meta_group_id' => (int) $metaGroupId,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= 500) {
                DB::table('eve_item_types')->upsert(
                    $batch,
                    ['type_id'],
                    ['meta_group_id', 'updated_at']
                );
                $imported += count($batch);
                $batch = [];
                $this->output->write('.');
            }
        }

        fclose($handle);

        if ($batch) {
            DB::table('eve_item_types')->upsert(
                $batch,
                ['type_id'],
                ['meta_group_id', 'updated_at']
            );
            $imported += count($batch);
        }

        $this->newLine();
        $this->info("Imported meta group IDs for {$imported} EVE item types.");
        $this->info('Tech II items use meta group ID 2.');
        return self::SUCCESS;
    }
}
