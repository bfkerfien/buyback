<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ApplyHoloPiPayoutRates extends Command
{
    protected $signature = 'buyback:apply-holo-pi-rates {--apply : Save the calculated individual PI payout rates; without this option the command only previews them}';
    protected $description = 'Match Holo PI appraisal rates where Holo pays more, and stay 1% above Holo where Buyback already pays more.';

    /**
     * Rates shown in Holo's 1,000-unit appraisal. The boolean is true for
     * items where Buyback's submitted ISK value was already higher.
     * For those items the target is 1% above Holo's payout (relative, not
     * one percentage point). All other items are set to Holo's displayed rate.
     */
    private const HOLO_RATES = [
        'Bacteria' => ['holo' => 77.00, 'keep_one_percent_above' => true],
        'Biofuels' => ['holo' => 78.00, 'keep_one_percent_above' => true],
        'Biomass' => ['holo' => 85.00, 'keep_one_percent_above' => true],
        'Chiral Structures' => ['holo' => 82.00, 'keep_one_percent_above' => true],
        'Electrolytes' => ['holo' => 80.00, 'keep_one_percent_above' => true],
        'Industrial Fibers' => ['holo' => 83.00, 'keep_one_percent_above' => true],
        'Oxidizing Compound' => ['holo' => 83.00, 'keep_one_percent_above' => true],
        'Oxygen' => ['holo' => 78.00, 'keep_one_percent_above' => true],
        'Plasmoids' => ['holo' => 82.00, 'keep_one_percent_above' => true],
        'Precious Metals' => ['holo' => 84.00, 'keep_one_percent_above' => true],
        'Proteins' => ['holo' => 85.00, 'keep_one_percent_above' => true],
        'Reactive Metals' => ['holo' => 77.00, 'keep_one_percent_above' => true],
        'Silicon' => ['holo' => 84.00, 'keep_one_percent_above' => true],
        'Toxic Metals' => ['holo' => 81.00, 'keep_one_percent_above' => true],
        'Water' => ['holo' => 78.00, 'keep_one_percent_above' => true],
        'Biocells' => ['holo' => 89.00, 'keep_one_percent_above' => false],
        'Construction Blocks' => ['holo' => 89.00, 'keep_one_percent_above' => false],
        'Consumer Electronics' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Coolant' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Enriched Uranium' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Fertilizer' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Genetically Enhanced Livestock' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Livestock' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Mechanical Parts' => ['holo' => 89.00, 'keep_one_percent_above' => false],
        'Microfiber Shielding' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Miniature Electronics' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Nanites' => ['holo' => 89.00, 'keep_one_percent_above' => false],
        'Oxides' => ['holo' => 89.00, 'keep_one_percent_above' => false],
        'Polyaramids' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Polytextiles' => ['holo' => 89.00, 'keep_one_percent_above' => false],
        'Rocket Fuel' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Silicate Glass' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Superconductors' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Supertensile Plastics' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Synthetic Oil' => ['holo' => 89.00, 'keep_one_percent_above' => false],
        'Test Cultures' => ['holo' => 89.00, 'keep_one_percent_above' => false],
        'Transmitter' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Viral Agent' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Water-Cooled CPU' => ['holo' => 88.00, 'keep_one_percent_above' => false],
        'Biotech Research Reports' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Camera Drones' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Condensates' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Cryoprotectant Solution' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Data Chips' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Gel-Matrix Biopaste' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Guidance Systems' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Hazmat Detection Systems' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Hermetic Membranes' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'High-Tech Transmitters' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Industrial Explosives' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Neocoms' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Nuclear Reactors' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Planetary Vehicles' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Robotics' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Smartfab Units' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Supercomputers' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Synthetic Synapses' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Transcranial Microcontrollers' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Ukomi Superconductors' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Vaccines' => ['holo' => 80.00, 'keep_one_percent_above' => true],
        'Broadcast Node' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Integrity Response Drones' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Nano-Factory' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Organic Mortar Applicators' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Recursive Computing Module' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Self-Harmonizing Power Core' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Sterile Conduits' => ['holo' => 90.00, 'keep_one_percent_above' => false],
        'Wetware Mainframe' => ['holo' => 90.00, 'keep_one_percent_above' => false],
    ];

    public function handle(): int
    {
        $names = array_keys(self::HOLO_RATES);
        $items = DB::table('eve_item_types as item')
            ->join('eve_item_groups as groups', 'groups.group_id', '=', 'item.group_id')
            ->where('item.published', true)
            ->where('groups.category_id', 43)
            ->whereIn('groups.group_id', [1042, 1034, 1040, 1041])
            ->whereIn('item.type_name', $names)
            ->get(['item.type_id', 'item.type_name', 'groups.group_id'])
            ->keyBy('type_name');

        $missing = array_values(array_diff($names, $items->keys()->all()));
        if ($missing) {
            $this->error('Cannot apply rates: these PI commodities were not found in the imported SDE: ' . implode(', ', $missing));
            return self::FAILURE;
        }

        $rows = [];
        foreach (self::HOLO_RATES as $name => $rule) {
            $target = round((float) $rule['holo'] * ($rule['keep_one_percent_above'] ? 1.01 : 1.0), 2);
            $item = $items->get($name);
            $rows[] = [
                'type_id' => (int) $item->type_id,
                'type_name' => $name,
                'buy_percent' => number_format($target, 2, '.', ''),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $tag = $rule['keep_one_percent_above'] ? 'Holo +1%' : 'Match Holo';
            $this->line(str_pad($name, 34) . str_pad(number_format($target, 2) . '%', 10) . $tag);
        }

        $this->newLine();
        $this->info('Validated ' . count($rows) . ' of ' . count(self::HOLO_RATES) . ' PI commodities across P1-P4.');

        if (!$this->option('apply')) {
            $this->comment('Preview only. No database changes were made. Re-run with --apply to save these rates.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($rows): void {
            foreach ($rows as $row) {
                DB::table('appraisal_pi_buy_rules')->updateOrInsert(
                    ['type_id' => $row['type_id']],
                    [
                        'type_name' => $row['type_name'],
                        'buy_percent' => $row['buy_percent'],
                        'created_at' => $row['created_at'],
                        'updated_at' => $row['updated_at'],
                    ]
                );
            }
        });

        $this->info('Saved all ' . count($rows) . ' individual PI payout rates.');
        $this->comment('These rates now override the shared PI category rate for the 68 listed commodities.');
        return self::SUCCESS;
    }
}
