<?php

namespace App\Console\Commands;

use App\Services\Eve\IncomingCharacterContractNotifier;
use Illuminate\Console\Command;

class NotifyIncomingEveContracts extends Command
{
    protected $signature = 'buyback:notify-incoming-contracts';
    protected $description = 'Notify Discord when My Eve Online receives a new personal EVE contract.';

    public function handle(IncomingCharacterContractNotifier $notifier): int
    {
        $result = $notifier->poll();
        $this->line($result['message']);

        return in_array($result['status'], ['ok', 'partial', 'disabled', 'missing_character', 'reauthorize'], true)
            ? self::SUCCESS
            : self::FAILURE;
    }
}
