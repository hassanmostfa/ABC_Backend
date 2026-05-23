<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderCancellationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CancelLegacyPendingOnlineOrders extends Command
{
    protected $signature = 'orders:cancel-legacy-pending-online {--dry-run : List orders without cancelling}';

    protected $description = 'Cancel legacy online_link orders with pending invoices before pay-first rollout';

    public function handle(OrderCancellationService $orderCancellationService): int
    {
        $orders = Order::query()
            ->where('payment_method', 'online_link')
            ->where('status', '!=', 'cancelled')
            ->whereHas('invoice', fn ($q) => $q->where('status', 'pending'))
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No legacy pending online orders found.');

            return self::SUCCESS;
        }

        $this->info('Found ' . $orders->count() . ' legacy pending online order(s).');

        foreach ($orders as $order) {
            $this->line('- ' . $order->order_number . ' (ID ' . $order->id . ')');

            if ($this->option('dry-run')) {
                continue;
            }

            $result = $orderCancellationService->cancelOrder(
                $order->id,
                'Cancelled during pay-first online payment migration'
            );

            if (!$result['success']) {
                $this->error('Failed to cancel ' . $order->order_number . ': ' . $result['message']);
                Log::warning('Legacy pending online order cancellation failed', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'message' => $result['message'],
                ]);
                continue;
            }

            $this->info('Cancelled ' . $order->order_number);
        }

        return self::SUCCESS;
    }
}
