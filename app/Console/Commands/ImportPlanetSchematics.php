<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportPlanetSchematics extends Command
{
    protected $signature = 'buyback:import-planet-schematics {file : Path to CCP SDE planetSchematics.jsonl}';
    protected $description = 'Import CCP planetary-industry schematic recipes and material quantities.';

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

        $schematics = [];
        $inputs = [];
        $skipped = 0;
        $lineNumber = 0;
        $now = now();

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            try {
                $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                $this->warn("Skipping invalid JSON on line {$lineNumber}.");
                $skipped++;
                continue;
            }

            $schematicId = $row['_key'] ?? $row['schematicID'] ?? $row['schematic_id'] ?? null;
            $name = $row['name']['en'] ?? $row['name'] ?? $row['schematicName'] ?? null;
            $cycleTime = $row['cycleTime'] ?? $row['cycle_time'] ?? null;
            if (!$schematicId || !is_numeric($schematicId) || !is_string($name) || trim($name) === '' || !is_numeric($cycleTime)) {
                $skipped++;
                continue;
            }

            $inputRows = [];
            $outputRows = [];
            foreach (($row['types'] ?? []) as $type) {
                $typeId = $type['_key'] ?? $type['typeID'] ?? $type['type_id'] ?? null;
                $quantity = $type['quantity'] ?? null;
                if (!$typeId || !is_numeric($typeId) || !is_numeric($quantity) || (int) $quantity < 1) {
                    continue;
                }

                if (($type['isInput'] ?? $type['is_input'] ?? false) === true) {
                    $inputRows[] = ['type_id' => (int) $typeId, 'quantity' => (int) $quantity];
                } else {
                    $outputRows[] = ['type_id' => (int) $typeId, 'quantity' => (int) $quantity];
                }
            }

            // A production schematic must have inputs and exactly one output.
            if (!$inputRows || count($outputRows) !== 1) {
                $skipped++;
                continue;
            }

            $schematics[] = [
                'schematic_id' => (int) $schematicId,
                'name' => trim($name),
                'cycle_time' => (int) $cycleTime,
                'product_type_id' => $outputRows[0]['type_id'],
                'product_quantity' => $outputRows[0]['quantity'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            foreach ($inputRows as $input) {
                $inputs[] = [
                    'schematic_id' => (int) $schematicId,
                    'type_id' => $input['type_id'],
                    'quantity' => $input['quantity'],
                ];
            }
        }
        fclose($handle);

        if (!$schematics) {
            $this->error('No valid planetary schematics were found. Check that this is the complete CCP planetSchematics.jsonl file.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($schematics, $inputs) {
            DB::table('eve_planet_schematic_inputs')->delete();
            DB::table('eve_planet_schematics')->delete();
            DB::table('eve_planet_schematics')->insert($schematics);
            if ($inputs) {
                foreach (array_chunk($inputs, 500) as $batch) {
                    DB::table('eve_planet_schematic_inputs')->insert($batch);
                }
            }
        });

        $this->info('Imported ' . count($schematics) . ' planetary schematics and ' . count($inputs) . ' input-material rows.');
        $this->comment("Skipped {$skipped} invalid or non-production schematic records.");
        $this->comment('This replaces the previous planetary schematic recipe data. Use the complete CCP planetSchematics.jsonl file.');
        return self::SUCCESS;
    }
}
