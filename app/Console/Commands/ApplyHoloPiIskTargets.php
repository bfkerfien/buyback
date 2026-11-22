<?php

namespace App\\Console\\Commands;

use Illuminate\\Console\\Command;
use Illuminate\\Support\\Facades\\DB;
use RuntimeException;

class ApplyHoloPiIskTargets extends Command
{
    protected $signature = 'buyback:apply-holo-pi-isk-targets {--apply : Save calculated individual PI payout rates; otherwise preview only}';
    protected $description = 'Calculate individual PI payout percentages that match known Holo 1,000-unit ISK payouts using current Jita buy snapshots.';

    /**
     * Exact Holo payout values for 1,000 units. Bacteria intentionally remains
     * 1% above Holo, matching the prior comparison decision. All other 67
     * commodity targets match the supplied Holo benchmark exactly.
     */
    private const TARGETS = [
        'Bacteria' => 304596.00 * 1.01,
        'Biocells' => 8396622.00,
        'Biofuels' => 336469.38,
        'Biomass' => 712812.55,
        'Biotech Research Reports' => 103320000.00,
        'Broadcast Node' => 1638900000.00,
        'Camera Drones' => 74862000.00,
        'Chiral Structures' => 517625.00,
        'Condensates' => 54540000.00,
        'Construction Blocks' => 8712210.00,
        'Consumer Electronics' => 11151000.00,
        'Coolant' => 9747000.00,
        'Cryoprotectant Solution' => 103770000.00,
        'Data Chips' => 81972000.00,
        'Electrolytes' => 421210.40,
        'Enriched Uranium' => 12429000.00,
        'Fertilizer' => 10080000.00,
        'Gel-Matrix Biopaste' => 109890000.00,
        'Genetically Enhanced Livestock' => 9756000.00,
        'Guidance Systems' => 68274000.00,
        'Hazmat Detection Systems' => 121950000.00,
        'Hermetic Membranes' => 91890000.00,
        'High-Tech Transmitters' => 88416000.00,
        'Industrial Explosives' => 68994000.00,
        'Industrial Fibers' => 597875.56,
        'Integrity Response Drones' => 2150100000.00,
        'Livestock' => 11250000.00,
        'Mechanical Parts' => 8861730.00,
        'Microfiber Shielding' => 14211000.00,
        'Miniature Electronics' => 10809000.00,
        'Nanites' => 6609140.00,
        'Nano-Factory' => 933300000.00,
        'Neocoms' => 68841000.00,
        'Nuclear Reactors' => 74214000.00,
        'Organic Mortar Applicators' => 892260000.00,
        'Oxides' => 8597400.00,
        'Oxidizing Compound' => 581109.56,
        'Oxygen' => 338281.32,
        'Planetary Vehicles' => 96660000.00,
        'Plasmoids' => 522925.48,
        'Polyaramids' => 11430000.00,
        'Polytextiles' => 8374010.00,
        'Precious Metals' => 638590.68,
        'Proteins' => 712555.00,
        'Reactive Metals' => 305013.94,
        'Recursive Computing Module' => 1203300000.00,
        'Robotics' => 74709000.00,
        'Rocket Fuel' => 10404000.00,
        'Self-Harmonizing Power Core' => 1711800000.00,
        'Silicate Glass' => 11430000.00,
        'Silicon' => 691955.04,
        'Smartfab Units' => 60273000.00,
        'Sterile Conduits' => 1107000000.00,
        'Supercomputers' => 87318000.00,
        'Superconductors' => 9693000.00,
        'Supertensile Plastics' => 12339000.00,
        'Synthetic Oil' => 6519250.00,
        'Synthetic Synapses' => 76023000.00,
        'Test Cultures' => 8730900.00,
        'Toxic Metals' => 456663.42,
        'Transcranial Microcontrollers' => 59058000.00,
        'Transmitter' => 10782000.00,
        'Ukomi Superconductors' => 66798000.00,
        'Vaccines' => 64736960.00,
        'Viral Agent' => 11529000.00,
        'Water' => 351437.58,
        'Water-Cooled CPU' => 5987520.00,
        'Wetware Mainframe' => 1865700000.00,
    ];

    public function handle(): int
    {
        $regionId = (string) config('buyback.region_id', '10000002');
        $rows = [];

        foreach (self::TARGETS as $name => $targetPayout) {
            $item = DB::table('eve_item_types as item')
                ->join('eve_item_groups as groups', 'groups.group_id', '=', 'item.group_id')
                ->where('item.published', true)
                ->where('groups.category_id', 43)
                ->whereIn('groups.group_id', [1042, 1034, 1040, 1041])
                ->where('item.type_name', $name)
                ->first(['item.type_id', 'item.type_name']);

            if (!$item) {
                $this->error("Missing published PI commodity in SDE: {$name}");
                return self::FAILURE;
            }

            $snapshot = DB::table('market_price_current')
                ->where('region_id', $regionId)
                ->where('type_id', $item->type_id)
                ->whereNotNull('buy_snapshot_id')
                ->whereNotNull('buy_price')
                ->where('buy_price', '>', 0)
                ->orderByDesc('buy_snapshot_id')
                ->first(['buy_price', 'buy_snapshot_id', 'buy_observed_at']);

            if (!$snapshot) {
                $this->error("Missing current Jita buy snapshot for {$name} (type ID {$item->type_id}). No rates were changed.");
                return self::FAILURE;
            }

            // PI is appraised from the commodity's own Jita buy price.
            // Calculator line_value = unit buy price * submitted quantity.
            $grossValue = (float) $snapshot->buy_price * 1000;
            $rate = round(((float) $targetPayout / $grossValue) * 100, 2);

            if (!is_finite($rate) || $rate <= 0 || $rate > 999.99) {
                $this->error("Calculated rate for {$name} is outside the supported decimal(5,2) range: {$rate}%");
                return self::FAILURE;
            }

            $existing = DB::table('appraisal_pi_buy_rules')
                ->where('type_id', $item->type_id)
                ->value('buy_percent');

            $rows[] = [
                'type_id' => (int) $item->type_id,
                'type_name' => $name,
                'old_rate' => $existing,
                'new_rate' => number_format($rate, 2, '.', ''),
                'target' => (float) $targetPayout,
                'gross' => $grossValue,
                'projected' => round($grossValue * $rate / 100, 2),
                'observed_at' => $snapshot->buy_observed_at,
            ];
        }

        $this->table(
            ['Commodity', 'Old rate', 'New rate', 'Holo target ISK', 'Projected ISK', 'Jita snapshot'],
            array_map(fn (array $row) => [
                $row['type_name'],
                $row['old_rate'] === null ? 'none' : number_format((float) $row['old_rate'], 2) . '%',
                $row['new_rate'] . '%',
                number_format($row['target'], 2),
                number_format($row['projected'], 2),
                $row['observed_at'] ?? 'unknown',
            ], $rows)
        );

        $this->newLine();
        $this->info('Validated ' . count($rows) . ' of ' . count(self::TARGETS) . ' known Holo ISK targets.');

        if (!$this->option('apply')) {
            $this->comment('Preview only; no database changes made. Re-run with --apply to save these rates.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($rows): void {
            foreach ($rows as $row) {
                DB::table('appraisal_pi_buy_rules')->updateOrInsert(
                    ['type_id' => $row['type_id']],
                    [
                        'type_name' => $row['type_name'],
                        'buy_percent' => $row['new_rate'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        });

        $this->info('Saved ' . count($rows) . ' individual PI ISK-target payout rates.');
        $this->comment('The remaining PI commodity rates were not changed.');
        return self::SUCCESS;
    }
}
