<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\Order;
use App\Services\Points\OrderPointsService;
use App\Services\Referral\ReferralRewardService;
use Illuminate\Support\Facades\Log;

class InvoiceObserver
{
    public function updated(Invoice $invoice): void
    {
        if (!$invoice->wasChanged('status') || $invoice->status !== 'paid' || !$invoice->order_id) {
            return;
        }

        $customerId = Order::query()->whereKey($invoice->order_id)->value('customer_id');
        if (!$customerId) {
            return;
        }

        try {
            app(ReferralRewardService::class)->awardIfQualified((int) $customerId);
        } catch (\Throwable $e) {
            Log::warning('Failed to award referral points after invoice payment', [
                'invoice_id' => $invoice->id,
                'order_id' => $invoice->order_id,
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            app(OrderPointsService::class)->awardIfQualified((int) $invoice->order_id);
        } catch (\Throwable $e) {
            Log::warning('Failed to award order points after invoice payment', [
                'invoice_id' => $invoice->id,
                'order_id' => $invoice->order_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
