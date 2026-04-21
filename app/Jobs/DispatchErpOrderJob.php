<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\ErpOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchErpOrderJob
{
    use Dispatchable, Queueable, SerializesModels;

    public function __construct(public int $orderId)
    {
    }

    public function handle(ErpOrderService $erpOrderService): void
    {
        $order = Order::find($this->orderId);
        if (!$order) {
            return;
        }

        $order->loadMissing(['items.variant', 'invoice', 'customer', 'charity']);

        try {
            if (in_array($order->payment_method, ['cash', 'wallet'], true)) {
                $erpOrderService->dispatchAfterCashOrWalletOrderCreated($order);
                return;
            }

            if ($order->payment_method === 'online_link') {
                $erpOrderService->dispatchAfterOnlineInvoicePaid($order);
            }
        } catch (\Throwable $e) {
            Log::warning('DispatchErpOrderJob failed', [
                'order_id' => $this->orderId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
