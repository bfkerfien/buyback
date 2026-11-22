<?php

namespace App\Jobs;

use App\Services\DiscordAppraisalNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDiscordAppraisalNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [30, 60, 120, 300];

    /**
     * @param array{token:string, kind:string, payout_value:float|int|string, origin_system:?string, character_name:?string, item_count:int} $appraisal
     */
    public function __construct(public array $appraisal)
    {
    }

    public function handle(DiscordAppraisalNotifier $notifier): void
    {
        $notifier->send($this->appraisal);
    }
}
