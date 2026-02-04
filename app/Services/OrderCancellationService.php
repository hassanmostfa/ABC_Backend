<?php

namespace App\Services;

use App\Models\Order;
use App\Models\RefundRequest;
use App\Repositories\OrderRepositoryInterface;
use App\Repositories\InvoiceRepositoryInterface;
use Illuminate\Support\Facades\DB;

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
     * Cancel an order. Handles refund based on payment method if invoice was paid.
     *
     * @param int $orderId
     * @param string|null $reason Optional reason for cancellation
     * @return array{success: bool, message: string, refund_request?: RefundRequest}
     * @throws \Exception
     */
    public function cancelOrder(int $orderId, ?string $reason = null): array
    {
        $order = $this->orderRepository->findById($orderId);
        if (!$order) {
            return ['success' => false, 'message' => 'Order not found'];
        }

        if ($order->status === 'cancelled') {
            return ['success' => false, 'message' => 'Order is already cancelled'];
        }

        $invoice = $this->invoiceRepository->getByOrder($orderId);
        $paymentMethod = $order->payment_method;
        $isPaid = $invoice && $invoice->status === 'paid';

        $refundRequest = null;

        try {
            DB::beginTransaction();

            // Refund points if used
            if ($invoice && $invoice->used_points > 0 && $order->customer_id) {
                $this->pointsService->refundPoints($order->customer_id, $invoice->used_points);
            }

            if ($isPaid) {
                switch ($paymentMethod) {
                    case 'cash':
                        // Cash on delivery - do nothing with money, just mark invoice cancelled
                        $this->invoiceService->markAsCancelled($invoice->id);
                        break;

                    case 'wallet':
                        // Return money to wallet
                        $this->walletService->addBalance($order->customer_id, $invoice->amount_due);
                        $this->invoiceService->markAsRefunded($invoice->id);
                        break;

                    case 'online_link':
                        // Create refund request for admin approval - money returned when admin approves
                        $refundRequest = RefundRequest::create([
                            'order_id' => $order->id,
                            'invoice_id' => $invoice->id,
                            'customer_id' => $order->customer_id,
                            'amount' => $invoice->amount_due,
                            'status' => RefundRequest::STATUS_PENDING,
                            'reason' => $reason,
                        ]);
                        $refundRequest->load(['order', 'customer']);
                        $this->invoiceService->markAsCancelled($invoice->id);
                        break;

                    default:
                        // Unknown payment method - treat as cash (do nothing)
                        break;
                }
            } else {
                // Invoice not paid - just mark as cancelled
                if ($invoice) {
                    $this->invoiceService->markAsCancelled($invoice->id);
                }
            }

            $this->orderRepository->update($orderId, ['status' => 'cancelled']);

            DB::commit();
            return [
                'success' => true,
                'message' => $paymentMethod === 'online_link' && $isPaid
                    ? 'Order cancelled. Refund request created and pending admin approval.'
                    : 'Order cancelled successfully',
                'refund_request' => $refundRequest,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
