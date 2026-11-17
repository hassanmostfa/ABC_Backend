<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\Points\OrderPointsService;
use App\Services\Referral\ReferralRewardService;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    public function updated(Order $order): void
    {
        if (!$order->wasChanged('status') || $order->status !== 'completed' || !$order->customer_id) {
            return;
        }

        try {
            app(ReferralRewardService::class)->awardIfQualified((int) $order->customer_id);
        } catch (\Throwable $e) {
            Log::warning('Failed to award referral points after order completion', [
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->awardOrderPoints($order->id);
    }

    private function awardOrderPoints(int $orderId): void
    {
        try {
            app(OrderPointsService::class)->awardIfQualified($orderId);
        } catch (\Throwable $e) {
            Log::warning('Failed to award order points after order completion', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
