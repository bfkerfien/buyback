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
     * 1% above Holo, matching the prior comparison decision. The other targets
     * match Holo exactly. Commodities without a verified Holo ISK target are
     * deliberately left unchanged.
     */
    private const TARGETS = [
        'Bacteria' => 304596.00 * 1.01,
        'Biocells' => 8396622.00,
        'Broadcast Node' => 1642500000.00,
        'Integrity Response Drones' => 2150100000.00,
        'Nano-Factory' => 937800000.00,
        'Organic Mortar Applicators' => 892260000.00,
        'Recursive Computing Module' => 1203300000.00,
        'Self-Harmonizing Power Core' => 1717200000.00,
        'Wetware Mainframe' => 1890000000.00,
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
