<?php

namespace App\Console\Commands;

use App\Services\Campaigns\WinbackCampaignService;
use Illuminate\Console\Command;

class SendWinbackCoupons extends Command
{
    protected $signature = 'customers:send-winback-coupons';

    protected $description = 'Send a percentage coupon to customers who have not ordered for the configured number of days';

    public function handle(WinbackCampaignService $winbackCampaignService): int
    {
        $sent = $winbackCampaignService->sendDueCoupons();
        $this->info("Sent {$sent} win-back coupon(s).");

        return self::SUCCESS;
    }
}
