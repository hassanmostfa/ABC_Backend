<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOrderCreatedNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $orderId
    ) {}

    public function handle(): void
    {
        $order = Order::find($this->orderId);
        if (!$order) {
            return;
        }

        try {
            if ($order->customer_id) {
                sendNotification(
                    null,
                    $order->customer_id,
                    'Order Created',
                    "Your order {$order->order_number} has been created successfully.",
                    'order',
                    ['order_id' => $order->id, 'order_number' => $order->order_number, 'status' => $order->status],
                    'تم إنشاء الطلب',
                    "تم إنشاء طلبك رقم {$order->order_number} بنجاح."
                );
            }

            sendNotification(
                null,
                null,
                'New Order',
                "A new order {$order->order_number} has been created.",
                'order',
                ['order_id' => $order->id, 'order_number' => $order->order_number, 'status' => $order->status],
                'طلب جديد',
                "تم إنشاء طلب جديد برقم {$order->order_number}."
            );
        } catch (\Throwable $e) {
            Log::warning('SendOrderCreatedNotificationsJob failed', [
                'order_id' => $this->orderId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
