<?php

namespace App\Services;

use App\Models\Order;
use App\Models\RefundRequest;
use App\Repositories\InvoiceRepositoryInterface;
use App\Repositories\OrderRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RefundRequestService
{
    public function __construct(
        protected WalletService $walletService,
        protected InvoiceService $invoiceService,
        protected OrderRepositoryInterface $orderRepository,
        protected InvoiceRepositoryInterface $invoiceRepository,
        protected OrderCancellationService $orderCancellationService
    ) {}

    /**
     * Move a paid order to refund status and create a pending refund request.
     *
     * @return array{success: bool, message: string, order?: Order, refund_request?: RefundRequest}
     */
    public function requestForPaidOrder(int $orderId, ?string $reason = null): array
    {
        $order = $this->orderRepository->findById($orderId);
        if (!$order) {
            return ['success' => false, 'message' => 'Order not found'];
        }

        if (in_array($order->status, ['cancelled', 'completed'], true)) {
            return ['success' => false, 'message' => 'Cannot request refund for a ' . $order->status . ' order'];
        }

        if ($order->status === 'refund') {
            $existing = RefundRequest::where('order_id', $order->id)
                ->where('status', RefundRequest::STATUS_PENDING)
                ->first();

            return [
                'success' => true,
                'message' => 'Order is already in refund status',
                'order' => $order->fresh(['customer', 'invoice', 'createdBy']),
                'refund_request' => $existing,
            ];
        }

        $invoice = $this->invoiceRepository->getByOrder($orderId);
        if (!$invoice || $invoice->status !== 'paid') {
            return ['success' => false, 'message' => 'Only paid orders can be moved to refund status'];
        }

        try {
            DB::beginTransaction();

            $refundRequest = RefundRequest::where('order_id', $order->id)
                ->where('invoice_id', $invoice->id)
                ->where('status', RefundRequest::STATUS_PENDING)
                ->first();

            if (!$refundRequest) {
                $refundRequest = RefundRequest::create([
                    'order_id' => $order->id,
                    'invoice_id' => $invoice->id,
                    'customer_id' => $order->customer_id,
                    'amount' => $invoice->amount_due,
                    'status' => RefundRequest::STATUS_PENDING,
                    'reason' => $reason,
                ]);
            } elseif ($reason && !$refundRequest->reason) {
                $refundRequest->update(['reason' => $reason]);
            }

            $this->orderRepository->update($orderId, ['status' => 'refund']);

            DB::commit();

            $refundRequest->load(['order', 'customer']);
            $this->notifyRefundRequestCreated($refundRequest);

            $order = $this->orderRepository->findById($orderId);
            if ($order) {
                $order->load(['customer', 'charity', 'offers', 'items.product', 'items.variant', 'invoice.payments', 'customerAddress', 'createdBy']);
            }

            return [
                'success' => true,
                'message' => 'Order moved to refund status. Refund request is pending approval.',
                'order' => $order,
                'refund_request' => $refundRequest,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Approve refund request - credit wallet, mark invoice refunded, cancel order.
     */
    public function approve(int $refundRequestId, ?string $adminNotes = null): array
    {
        $refundRequest = RefundRequest::with(['order', 'invoice', 'customer'])->find($refundRequestId);
        if (!$refundRequest) {
            return ['success' => false, 'message' => 'Refund request not found'];
        }

        if ($refundRequest->status !== RefundRequest::STATUS_PENDING) {
            return ['success' => false, 'message' => 'Refund request is already processed'];
        }

        try {
            DB::beginTransaction();

            $this->walletService->addBalance($refundRequest->customer_id, $refundRequest->amount);
            $this->invoiceService->markAsRefunded($refundRequest->invoice_id);

            $refundRequest->update([
                'status' => RefundRequest::STATUS_APPROVED,
                'admin_notes' => $adminNotes ?? $refundRequest->admin_notes,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            $cancelResult = $this->orderCancellationService->cancelOrder(
                $refundRequest->order_id,
                $refundRequest->reason,
                true
            );

            if (!$cancelResult['success']) {
                throw new \Exception($cancelResult['message']);
            }

            DB::commit();

            $this->notifyCustomerRefundStatus($refundRequest, RefundRequest::STATUS_APPROVED);

            return [
                'success' => true,
                'message' => 'Refund approved. Money has been added to customer wallet and order cancelled.',
                'refund_request' => $refundRequest->fresh(['order', 'invoice', 'customer']),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Reject refund request and return order to pending.
     */
    public function reject(int $refundRequestId, ?string $adminNotes = null): array
    {
        $refundRequest = RefundRequest::with(['order'])->find($refundRequestId);
        if (!$refundRequest) {
            return ['success' => false, 'message' => 'Refund request not found'];
        }

        if ($refundRequest->status !== RefundRequest::STATUS_PENDING) {
            return ['success' => false, 'message' => 'Refund request is already processed'];
        }

        try {
            DB::beginTransaction();

            $refundRequest->update([
                'status' => RefundRequest::STATUS_REJECTED,
                'admin_notes' => $adminNotes ?? $refundRequest->admin_notes,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            $order = $refundRequest->order;
            if ($order && $order->status === 'refund') {
                $this->orderRepository->update($order->id, ['status' => 'pending']);
            }

            DB::commit();

            $this->notifyCustomerRefundStatus($refundRequest, RefundRequest::STATUS_REJECTED);

            return [
                'success' => true,
                'message' => 'Refund request rejected. Order returned to pending.',
                'refund_request' => $refundRequest->fresh(['order', 'invoice', 'customer']),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function notifyRefundRequestCreated(RefundRequest $refundRequest): void
    {
        try {
            sendNotification(
                null,
                null,
                'Refund Request Created',
                "Refund request #{$refundRequest->id} was created for order {$refundRequest->order?->order_number}.",
                'payment',
                [
                    'refund_request_id' => $refundRequest->id,
                    'order_id' => $refundRequest->order_id,
                    'invoice_id' => $refundRequest->invoice_id,
                ],
                'تم إنشاء طلب استرجاع',
                "تم إنشاء طلب استرجاع رقم {$refundRequest->id} للطلب {$refundRequest->order?->order_number}."
            );
        } catch (\Exception $e) {
            Log::warning('Failed to dispatch refund request created notification', [
                'refund_request_id' => $refundRequest->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify customer when refund request status changes.
     */
    protected function notifyCustomerRefundStatus(RefundRequest $refundRequest, string $status): void
    {
        try {
            $refundRequest->loadMissing(['order', 'customer']);

            $customerId = $refundRequest->customer_id ?: $refundRequest->order?->customer_id;
            if (!$customerId) {
                Log::warning('Refund status notification skipped: customer ID missing', [
                    'refund_request_id' => $refundRequest->id,
                    'status' => $status,
                ]);
                return;
            }

            $isApproved = $status === RefundRequest::STATUS_APPROVED;

            sendNotification(
                null,
                $customerId,
                $isApproved ? 'Refund Approved' : 'Refund Rejected',
                $isApproved
                    ? "Your refund request for order {$refundRequest->order?->order_number} has been approved and added to your wallet."
                    : "Your refund request for order {$refundRequest->order?->order_number} has been rejected.",
                'payment',
                [
                    'refund_request_id' => $refundRequest->id,
                    'order_id' => $refundRequest->order_id,
                    'invoice_id' => $refundRequest->invoice_id,
                    'amount' => $refundRequest->amount,
                    'status' => $status,
                ],
                $isApproved ? 'تمت الموافقة على الاسترجاع' : 'تم رفض طلب الاسترجاع',
                $isApproved
                    ? "تمت الموافقة على طلب الاسترجاع الخاص بالطلب {$refundRequest->order?->order_number} وتم إضافة المبلغ إلى محفظتك."
                    : "تم رفض طلب الاسترجاع الخاص بالطلب {$refundRequest->order?->order_number}."
            );
        } catch (\Exception $e) {
            Log::warning('Failed to dispatch refund status notification', [
                'refund_request_id' => $refundRequest->id,
                'status' => $status,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
