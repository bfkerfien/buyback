<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportEveTypeMaterials extends Command
{
    protected $signature = 'buyback:import-type-materials {file : Path to CCP SDE typeMaterials.jsonl}';
    protected $description = 'Import CCP SDE reprocessing material quantities for EVE item types.';

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

        // This command expects the complete CCP file; remove stale recipe rows before importing it.
        DB::table('eve_type_materials')->delete();

        $batch = [];
        $typeCount = 0;
        $count = 0;
        $lineNumber = 0;
        $now = now();

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            try {
                $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                $this->warn("Skipping invalid JSON on line {$lineNumber}.");
                continue;
            }

            $typeId = $row['_key'] ?? $row['typeID'] ?? $row['type_id'] ?? null;
            if (!$typeId || !is_numeric($typeId)) {
                continue;
            }

            $typeCount++;
            foreach (($row['materials'] ?? []) as $material) {
                $materialId = $material['materialTypeID'] ?? $material['material_type_id'] ?? null;
                $quantity = $material['quantity'] ?? null;
                if (!$materialId || !is_numeric($materialId) || !is_numeric($quantity) || (int) $quantity < 1) {
                    continue;
                }

                $batch[] = [
                    'type_id' => (int) $typeId,
                    'material_type_id' => (int) $materialId,
                    'quantity' => (int) $quantity,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($batch) >= 1000) {
                    DB::table('eve_type_materials')->upsert($batch, ['type_id', 'material_type_id'], ['quantity', 'updated_at']);
                    $count += count($batch);
                    $batch = [];
                    $this->output->write('.');
                }
            }
        }
        fclose($handle);

        if ($batch) {
            DB::table('eve_type_materials')->upsert($batch, ['type_id', 'material_type_id'], ['quantity', 'updated_at']);
            $count += count($batch);
        }

        $this->newLine();
        $this->info("Imported or updated {$count} material rows across {$typeCount} SDE type records.");
        $this->comment('This replaces the previous recipe table contents. Always use the complete CCP typeMaterials.jsonl file.');
        return self::SUCCESS;
    }
}
