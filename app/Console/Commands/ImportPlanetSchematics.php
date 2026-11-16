<?php

namespace App\\Console\\Commands;

use Illuminate\\Console\\Command;
use Illuminate\\Support\\Facades\\DB;
use Throwable;

class ImportPlanetSchematics extends Command
{
    protected $signature = 'buyback:import-planet-schematics {schematics : CCP planetSchematics.jsonl path}';
    protected $description = 'Import CCP planetary-industry schematic definitions and their inline input/output type mappings.';

    public function handle(): int
    {
        $path = $this->argument('schematics');
        if (!is_file($path) || !is_readable($path)) {
            $this->error("Cannot read SDE file: {$path}");
            return self::FAILURE;
        }

        $schematics = [];
        $inputs = [];
        $lineNumber = 0;
        $skipped = 0;
        $handle = fopen($path, 'rb');
        if (!$handle) {
            $this->error("Cannot open SDE file: {$path}");
            return self::FAILURE;
        }

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            try {
                $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                $this->warn("Skipping invalid schematic JSON on line {$lineNumber}.");
                $skipped++;
                continue;
            }

            $id = $row['_key'] ?? $row['schematicID'] ?? $row['schematic_id'] ?? null;
            $name = $row['name']['en'] ?? $row['schematicName'] ?? $row['name'] ?? null;
            $cycle = $row['cycleTime'] ?? $row['cycle_time'] ?? null;
            $types = $row['types'] ?? null;

            if (!$id || !is_numeric($id) || !is_string($name) || trim($name) === '' ||
                !is_numeric($cycle) || !is_array($types)) {
                $skipped++;
                continue;
            }

            $schematicId = (int) $id;
            $outputs = [];
            $schematicInputs = [];
            foreach ($types as $type) {
                if (!is_array($type)) {
                    continue;
                }
                $typeId = $type['_key'] ?? $type['typeID'] ?? $type['type_id'] ?? null;
                $quantity = $type['quantity'] ?? null;
                $isInput = filter_var($type['isInput'] ?? $type['is_input'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if (!$typeId || !is_numeric($typeId) || !is_numeric($quantity) ||
                    (int) $quantity < 1 || $isInput === null) {
                    continue;
                }

                $mapping = ['type_id' => (int) $typeId, 'quantity' => (int) $quantity];
                if ($isInput) {
                    $schematicInputs[] = ['schematic_id' => $schematicId] + $mapping;
                } else {
                    $outputs[] = $mapping;
                }
            }

            if (!$schematicInputs || count($outputs) !== 1) {
                $skipped++;
                continue;
            }

            $schematics[$schematicId] = [
                'schematic_id' => $schematicId,
                'name' => trim($name),
                'cycle_time' => (int) $cycle,
                'product_type_id' => $outputs[0]['type_id'],
                'product_quantity' => $outputs[0]['quantity'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
            array_push($inputs, ...$schematicInputs);
        }
        fclose($handle);

        if (!$schematics) {
            $this->error('No valid PI schematics were parsed from the supplied CCP planetSchematics.jsonl file.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($schematics, $inputs) {
            DB::table('eve_planet_schematic_inputs')->delete();
            DB::table('eve_planet_schematics')->delete();
            foreach (array_chunk(array_values($schematics), 500) as $batch) {
                DB::table('eve_planet_schematics')->insert($batch);
            }
            foreach (array_chunk($inputs, 1000) as $batch) {
                DB::table('eve_planet_schematic_inputs')->insert($batch);
            }
        });

        $this->info('Imported ' . count($schematics) . ' PI schematics and ' . count($inputs) . ' input mappings.');
        $this->comment("Skipped {$skipped} invalid or incomplete schematic rows.");
        return self::SUCCESS;
    }
}
