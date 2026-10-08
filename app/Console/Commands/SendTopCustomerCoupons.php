<?php

namespace App\Console\Commands;

use App\Services\Campaigns\TopCustomerCampaignService;
use Illuminate\Console\Command;

class SendTopCustomerCoupons extends Command
{
    protected $signature = 'customers:send-top-customer-coupons';

    protected $description = 'Send a percentage coupon to the customers with the most orders';

    public function handle(TopCustomerCampaignService $topCustomerCampaignService): int
    {
        $sent = $topCustomerCampaignService->sendDueCoupons();
        $this->info("Sent {$sent} top-customer coupon(s).");

        return self::SUCCESS;
    }
}
