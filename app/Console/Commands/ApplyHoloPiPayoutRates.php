<?php

namespace App\\Console\\Commands;

use Illuminate\\Console\\Command;

/**
 * Backwards-compatible entry point. The former implementation copied Holo's
 * displayed percentages, which did not guarantee matching the actual ISK
 * appraisal. Delegate to the live-price ISK-target implementation instead.
 */
class ApplyHoloPiPayoutRates extends Command
{
    protected $signature = 'buyback:apply-holo-pi-rates {--apply : Save calculated individual PI payout rates; otherwise preview only}';
    protected $description = 'Backwards-compatible alias for the live-price Holo PI ISK target correction.';

    public function handle(): int
    {
        $this->comment('Using actual Holo ISK targets and current Jita buy snapshots (not Holo percentage labels).');

        return $this->call('buyback:apply-holo-pi-isk-targets', [
            '--apply' => (bool) $this->option('apply'),
        ]);
    }
}
