<?php

namespace App\Services;

use App\Repositories\OrderRepositoryInterface;
use App\Repositories\InvoiceRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderCancellationService
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepository,
        protected InvoiceRepositoryInterface $invoiceRepository,
        protected InvoiceService $invoiceService,
        protected WalletService $walletService,
        protected PointsService $pointsService
    ) {}

    /**
     * Cancel an order.
     *
     * Paid orders cannot be cancelled directly — move them to refund status first.
     * After a refund request is approved, call with $fromRefundApproval = true.
     *
     * @return array{success: bool, message: string}
     * @throws \Exception
     */
    public function cancelOrder(int $orderId, ?string $reason = null, bool $fromRefundApproval = false): array
    {
        $order = $this->orderRepository->findById($orderId);
        if (!$order) {
            return ['success' => false, 'message' => 'Order not found'];
        }

        if ($order->status === 'cancelled') {
            return ['success' => false, 'message' => 'Order is already cancelled'];
        }

        $invoice = $this->invoiceRepository->getByOrder($orderId);
        $isPaid = $invoice && $invoice->status === 'paid';
        $invoiceAlreadyRefunded = $invoice && $invoice->status === 'refunded';

        if ($isPaid && !$fromRefundApproval) {
            return [
                'success' => false,
                'message' => 'Cannot cancel a paid order. Set status to refund instead.',
            ];
        }

        if ($order->status === 'refund' && !$fromRefundApproval) {
            return [
                'success' => false,
                'message' => 'Order has a pending refund. Approve or reject the refund request instead.',
            ];
        }

        try {
            DB::beginTransaction();

            // Refund points if used (skip if invoice already handled and points were cleared — still safe to refund once)
            if ($invoice && $invoice->used_points > 0 && $order->customer_id) {
                $this->pointsService->refundPoints($order->customer_id, $invoice->used_points);
            }

            if ($fromRefundApproval) {
                // Money already credited and invoice marked refunded by RefundRequestService::approve.
                // Only finalize order cancellation here.
                if ($invoice && !$invoiceAlreadyRefunded && $invoice->status !== 'cancelled') {
                    $this->invoiceService->markAsCancelled($invoice->id);
                }
            } elseif ($invoice) {
                // Unpaid (or non-paid) invoice — cancel invoice only
                $this->invoiceService->markAsCancelled($invoice->id);
            }

            $this->orderRepository->update($orderId, ['status' => 'cancelled']);

            DB::commit();

            $order = $this->orderRepository->findById($orderId);
            try {
                if ($order && $order->customer_id) {
                    sendNotification(
                        null,
                        $order->customer_id,
                        'Order Cancelled',
                        "Your order {$order->order_number} has been cancelled.",
                        'order',
                        ['order_id' => $order->id, 'order_number' => $order->order_number, 'status' => 'cancelled'],
                        'تم إلغاء الطلب',
                        "تم إلغاء طلبك رقم {$order->order_number}."
                    );
                }

                if ($order) {
                    sendNotification(
                        null,
                        null,
                        'Order Cancelled',
                        "Order {$order->order_number} was cancelled.",
                        'order',
                        ['order_id' => $order->id, 'order_number' => $order->order_number, 'status' => 'cancelled'],
                        'تم إلغاء الطلب',
                        "تم إلغاء الطلب رقم {$order->order_number}."
                    );
                }
            } catch (\Exception $e) {
                Log::warning('Failed to dispatch cancellation notifications', [
                    'order_id' => $orderId,
                    'message' => $e->getMessage(),
                ]);
            }

            return [
                'success' => true,
                'message' => 'Order cancelled successfully',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
