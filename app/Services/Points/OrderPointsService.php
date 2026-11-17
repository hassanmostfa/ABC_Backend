<?php

namespace App\Services\Points;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderPointsService
{
    public function __construct(
        protected PointsTransactionService $pointsTransactionService
    ) {}

    /**
     * Award spend points once, when the order is completed and its invoice is paid.
     */
    public function awardIfQualified(int $orderId): void
    {
        if ($orderId <= 0 || !$this->isEnabled()) {
            return;
        }

        $pointsPerKwd = $this->pointsPerKwd();
        if ($pointsPerKwd <= 0) {
            return;
        }

        $reward = DB::transaction(function () use ($orderId, $pointsPerKwd) {
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();

            if (
                !$order
                || !$order->customer_id
                || $order->status !== 'completed'
                || $order->order_points_awarded_at !== null
            ) {
                return null;
            }

            $invoice = $order->invoice()->first();
            if (!$invoice || $invoice->status !== 'paid') {
                return null;
            }

            $points = $this->pointsForAmount((float) $invoice->amount_due, $pointsPerKwd);

            $order->forceFill(['order_points_awarded_at' => now()])->save();

            if ($points <= 0) {
                return null;
            }

            $customer = Customer::query()->whereKey($order->customer_id)->lockForUpdate()->first();
            if (!$customer) {
                return null;
            }

            $customer->increment('points', $points);

            $this->pointsTransactionService->recordPointsEarned(
                $customer->id,
                $points,
                $order->id,
                "Earned {$points} points from order {$order->order_number}"
            );

            return [
                'customer_id' => $customer->id,
                'points' => $points,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ];
        });

        if ($reward === null) {
            return;
        }

        DB::afterCommit(function () use ($reward) {
            $this->notifyCustomer($reward);
        });
    }

    private function isEnabled(): bool
    {
        $enabled = Setting::getValue('order_points_enabled', '1');

        return $enabled === '1' || $enabled === 1;
    }

    private function pointsPerKwd(): int
    {
        $raw = Setting::getValue('order_points_per_kwd', '10');
        if (!is_numeric($raw)) {
            return 0;
        }

        return max(0, (int) $raw);
    }

    /**
     * Whole points for the paid amount. 1 KWD at a rate of 10 is 10 points; 1.250 KWD is 12 points.
     */
    private function pointsForAmount(float $amountDue, int $pointsPerKwd): int
    {
        $fils = (int) round($amountDue * 1000);

        return intdiv($fils * $pointsPerKwd, 1000);
    }

    /**
     * @param  array{customer_id: int, points: int, order_id: int, order_number: string}  $reward
     */
    private function notifyCustomer(array $reward): void
    {
        try {
            $points = $reward['points'];
            $orderNumber = $reward['order_number'];

            sendNotification(
                customerId: $reward['customer_id'],
                type: 'order_points',
                titleEn: 'Points earned',
                titleAr: 'نقاط مكتسبة',
                messageEn: "You earned {$points} points from order {$orderNumber}.",
                messageAr: "لقد ربحت {$points} نقطة من الطلب {$orderNumber}.",
                data: [
                    'points_earned' => $points,
                    'order_id' => (string) $reward['order_id'],
                    'order_number' => $orderNumber,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to send order points notification', [
                'order_id' => $reward['order_id'],
                'customer_id' => $reward['customer_id'],
                'error' => $e->getMessage(),
            ]);
        }
    }
}
