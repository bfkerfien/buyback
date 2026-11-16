<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportPlanetSchematics extends Command
{
    protected $signature = 'buyback:import-planet-schematics {schematics : CCP planetSchematics.jsonl path} {typeMap : CCP planetSchematicsTypeMap.jsonl path}';
    protected $description = 'Import CCP planetary-industry schematic definitions and their input/output type map.';

    private function rowData(array $row): array
    {
        $value = $row['_value'] ?? null;
        return is_array($value) ? array_merge($value, ['_key' => $row['_key'] ?? null]) : $row;
    }

    public function handle(): int
    {
        $schematicPath = $this->argument('schematics');
        $typeMapPath = $this->argument('typeMap');
        foreach ([$schematicPath, $typeMapPath] as $path) {
            if (!is_file($path) || !is_readable($path)) {
                $this->error("Cannot read SDE file: {$path}");
                return self::FAILURE;
            }
        }

        $schematics = [];
        $handle = fopen($schematicPath, 'rb');
        if (!$handle) {
            $this->error("Cannot open SDE file: {$schematicPath}");
            return self::FAILURE;
        }

        $skippedSchematics = 0;
        $lineNumber = 0;
        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            try {
                $row = $this->rowData(json_decode($line, true, 512, JSON_THROW_ON_ERROR));
            } catch (Throwable) {
                $this->warn("Skipping invalid schematic JSON on line {$lineNumber}.");
                $skippedSchematics++;
                continue;
            }

            $id = $row['_key'] ?? $row['schematicID'] ?? $row['schematic_id'] ?? null;
            $name = $row['schematicName'] ?? $row['name']['en'] ?? $row['name'] ?? null;
            $cycle = $row['cycleTime'] ?? $row['cycle_time'] ?? null;
            if (!$id || !is_numeric($id) || !is_string($name) || trim($name) === '' || !is_numeric($cycle)) {
                $skippedSchematics++;
                continue;
            }
            $schematics[(int) $id] = [
                'schematic_id' => (int) $id,
                'name' => trim($name),
                'cycle_time' => (int) $cycle,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        fclose($handle);

        if (!$schematics) {
            $this->error('No schematic definitions were parsed. Supply the complete current CCP planetSchematics.jsonl file.');
            return self::FAILURE;
        }

        $outputs = [];
        $inputs = [];
        $handle = fopen($typeMapPath, 'rb');
        if (!$handle) {
            $this->error("Cannot open SDE file: {$typeMapPath}");
            return self::FAILURE;
        }

        $skippedMappings = 0;
        $lineNumber = 0;
        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            try {
                $row = $this->rowData(json_decode($line, true, 512, JSON_THROW_ON_ERROR));
            } catch (Throwable) {
                $this->warn("Skipping invalid type-map JSON on line {$lineNumber}.");
                $skippedMappings++;
                continue;
            }

            $schematicId = $row['schematicID'] ?? $row['schematic_id'] ?? null;
            $typeId = $row['typeID'] ?? $row['type_id'] ?? null;
            $quantity = $row['quantity'] ?? null;
            $isInput = filter_var($row['isInput'] ?? $row['is_input'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if (!$schematicId || !is_numeric($schematicId) || !isset($schematics[(int) $schematicId]) ||
                !$typeId || !is_numeric($typeId) || !is_numeric($quantity) || (int) $quantity < 1 || $isInput === null) {
                $skippedMappings++;
                continue;
            }

            $mapping = ['type_id' => (int) $typeId, 'quantity' => (int) $quantity];
            if ($isInput) {
                $inputs[] = ['schematic_id' => (int) $schematicId] + $mapping;
            } else {
                $outputs[(int) $schematicId][] = $mapping;
            }
        }
        fclose($handle);

        $validSchematics = [];
        foreach ($schematics as $id => $schematic) {
            $schematicOutputs = $outputs[$id] ?? [];
            if (!$inputsForSchematic = array_values(array_filter($inputs, fn ($input) => $input['schematic_id'] === $id))) {
                continue;
            }
            if (count($schematicOutputs) !== 1) {
                continue;
            }
            $schematic['product_type_id'] = $schematicOutputs[0]['type_id'];
            $schematic['product_quantity'] = $schematicOutputs[0]['quantity'];
            $validSchematics[$id] = $schematic;
        }

        if (!$validSchematics) {
            $this->error('Schematic definitions loaded, but no valid input/output mappings were found. Check the complete planetSchematicsTypeMap.jsonl file.');
            return self::FAILURE;
        }

        $validInputs = array_values(array_filter($inputs, fn ($input) => isset($validSchematics[$input['schematic_id']])));
        DB::transaction(function () use ($validSchematics, $validInputs) {
            DB::table('eve_planet_schematic_inputs')->delete();
            DB::table('eve_planet_schematics')->delete();
            foreach (array_chunk(array_values($validSchematics), 500) as $batch) {
                DB::table('eve_planet_schematics')->insert($batch);
            }
            foreach (array_chunk($validInputs, 1000) as $batch) {
                DB::table('eve_planet_schematic_inputs')->insert($batch);
            }
        });

        $this->info('Imported ' . count($validSchematics) . ' PI schematics and ' . count($validInputs) . ' input mappings.');
        $this->comment("Skipped {$skippedSchematics} schematic rows and {$skippedMappings} invalid/unmatched type-map rows.");
        return self::SUCCESS;
    }
}
