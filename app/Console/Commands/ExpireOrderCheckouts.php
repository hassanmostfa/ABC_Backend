<?php

namespace App\Console\Commands;

use App\Services\OrderCheckoutService;
use Illuminate\Console\Command;

class ExpireOrderCheckouts extends Command
{
    protected $signature = 'orders:expire-checkouts';

    protected $description = 'Expire stale pending order checkouts';

    public function handle(OrderCheckoutService $orderCheckoutService): int
    {
        $count = $orderCheckoutService->expireStaleCheckouts();
        $this->info("Expired {$count} checkout(s).");

        return self::SUCCESS;
    }
}
