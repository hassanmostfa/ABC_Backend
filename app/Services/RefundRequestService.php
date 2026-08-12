<?php

namespace App\Services;

use App\Models\Order;
use App\Models\RefundRequest;
use App\Repositories\Invoices\InvoiceRepositoryInterface;
use App\Repositories\Orders\OrderRepositoryInterface;
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
     * Create a pending refund request for remaining pending subscription orders.
     * Sets customer subscription to pending_cancellation until approved/rejected.
     *
     * @return array{success: bool, message: string, customer_subscription?: CustomerSubscription, refund_request?: RefundRequest}
     */
    public function requestForCustomerSubscription(int $customerSubscriptionId, ?string $reason = null): array
    {
        $customerSubscription = \App\Models\CustomerSubscription::with('orders')->find($customerSubscriptionId);
        if (!$customerSubscription) {
            return ['success' => false, 'message' => 'Customer subscription not found'];
        }

        if (in_array($customerSubscription->status, ['cancelled', 'completed'], true)) {
            return ['success' => false, 'message' => 'Cannot request refund for a ' . $customerSubscription->status . ' subscription'];
        }

        if ($customerSubscription->status === 'pending_cancellation') {
            $existing = RefundRequest::where('customer_subscription_id', $customerSubscription->id)
                ->where('status', RefundRequest::STATUS_PENDING)
                ->first();

            return [
                'success' => true,
                'message' => 'Subscription cancellation refund is already pending approval',
                'customer_subscription' => $customerSubscription,
                'refund_request' => $existing,
            ];
        }

        $pendingOrders = $customerSubscription->orders->where('status', 'pending');
        $refundAmount = round((float) $pendingOrders->sum('total_amount'), 3);

        if ($refundAmount <= 0 || $pendingOrders->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No pending subscription orders to refund',
            ];
        }

        try {
            DB::beginTransaction();

            $refundRequest = RefundRequest::create([
                'order_id' => null,
                'invoice_id' => null,
                'customer_id' => $customerSubscription->customer_id,
                'customer_subscription_id' => $customerSubscription->id,
                'amount' => $refundAmount,
                'status' => RefundRequest::STATUS_PENDING,
                'reason' => $reason ?? 'Subscription cancellation - refund remaining pending orders',
            ]);

            $customerSubscription->update(['status' => 'pending_cancellation']);

            DB::commit();

            $refundRequest->load(['customer', 'customerSubscription']);
            $this->notifySubscriptionRefundRequestCreated($refundRequest);

            return [
                'success' => true,
                'message' => 'Subscription cancellation refund request created. Pending approval to credit wallet.',
                'customer_subscription' => $customerSubscription->fresh(['subscription.offer', 'orders']),
                'refund_request' => $refundRequest,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Approve refund request - credit wallet, mark invoice refunded, cancel order.
     * For subscription refunds: credit wallet, cancel pending subscription orders, mark subscription cancelled.
     */
    public function approve(int $refundRequestId, ?string $adminNotes = null): array
    {
        $refundRequest = RefundRequest::with(['order', 'invoice', 'customer', 'customerSubscription.orders'])->find($refundRequestId);
        if (!$refundRequest) {
            return ['success' => false, 'message' => 'Refund request not found'];
        }

        if ($refundRequest->status !== RefundRequest::STATUS_PENDING) {
            return ['success' => false, 'message' => 'Refund request is already processed'];
        }

        try {
            DB::beginTransaction();

            $this->walletService->addBalance($refundRequest->customer_id, (float) $refundRequest->amount);

            if ($refundRequest->isSubscriptionRefund()) {
                $customerSubscription = $refundRequest->customerSubscription;
                if ($customerSubscription) {
                    $customerSubscription->orders()
                        ->where('status', 'pending')
                        ->update(['status' => 'cancelled']);

                    $customerSubscription->update(['status' => 'cancelled']);
                }
            } else {
                if ($refundRequest->invoice_id) {
                    $this->invoiceService->markAsRefunded($refundRequest->invoice_id);
                }

                $cancelResult = $this->orderCancellationService->cancelOrder(
                    $refundRequest->order_id,
                    $refundRequest->reason,
                    true
                );

                if (!$cancelResult['success']) {
                    throw new \Exception($cancelResult['message']);
                }
            }

            $refundRequest->update([
                'status' => RefundRequest::STATUS_APPROVED,
                'admin_notes' => $adminNotes ?? $refundRequest->admin_notes,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            DB::commit();

            $this->notifyCustomerRefundStatus($refundRequest, RefundRequest::STATUS_APPROVED);

            return [
                'success' => true,
                'message' => $refundRequest->isSubscriptionRefund()
                    ? 'Subscription refund approved. Pending orders cancelled and amount added to customer wallet.'
                    : 'Refund approved. Money has been added to customer wallet and order cancelled.',
                'refund_request' => $refundRequest->fresh(['order', 'invoice', 'customer', 'customerSubscription']),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Reject refund request.
     * For order refunds: return order to pending.
     * For subscription refunds: restore subscription to active.
     */
    public function reject(int $refundRequestId, ?string $adminNotes = null): array
    {
        $refundRequest = RefundRequest::with(['order', 'customerSubscription'])->find($refundRequestId);
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

            if ($refundRequest->isSubscriptionRefund()) {
                $customerSubscription = $refundRequest->customerSubscription;
                if ($customerSubscription && $customerSubscription->status === 'pending_cancellation') {
                    $customerSubscription->update(['status' => 'active']);
                }
            } else {
                $order = $refundRequest->order;
                if ($order && $order->status === 'refund') {
                    $this->orderRepository->update($order->id, ['status' => 'pending']);
                }
            }

            DB::commit();

            $this->notifyCustomerRefundStatus($refundRequest, RefundRequest::STATUS_REJECTED);

            return [
                'success' => true,
                'message' => $refundRequest->isSubscriptionRefund()
                    ? 'Subscription refund request rejected. Subscription remains active.'
                    : 'Refund request rejected. Order returned to pending.',
                'refund_request' => $refundRequest->fresh(['order', 'invoice', 'customer', 'customerSubscription']),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function notifySubscriptionRefundRequestCreated(RefundRequest $refundRequest): void
    {
        try {
            sendNotification(
                null,
                null,
                'Subscription Refund Request Created',
                "Refund request #{$refundRequest->id} was created for customer subscription #{$refundRequest->customer_subscription_id}. Amount: {$refundRequest->amount}",
                'payment',
                [
                    'refund_request_id' => $refundRequest->id,
                    'customer_subscription_id' => $refundRequest->customer_subscription_id,
                    'amount' => $refundRequest->amount,
                ],
                'تم إنشاء طلب استرجاع اشتراك',
                "تم إنشاء طلب استرجاع رقم {$refundRequest->id} للاشتراك رقم {$refundRequest->customer_subscription_id}."
            );
        } catch (\Exception $e) {
            Log::warning('Failed to dispatch subscription refund request created notification', [
                'refund_request_id' => $refundRequest->id,
                'message' => $e->getMessage(),
            ]);
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
            $refundRequest->loadMissing(['order', 'customer', 'customerSubscription']);

            $customerId = $refundRequest->customer_id ?: $refundRequest->order?->customer_id;
            if (!$customerId) {
                Log::warning('Refund status notification skipped: customer ID missing', [
                    'refund_request_id' => $refundRequest->id,
                    'status' => $status,
                ]);
                return;
            }

            $isApproved = $status === RefundRequest::STATUS_APPROVED;
            $isSubscription = $refundRequest->isSubscriptionRefund();
            $reference = $isSubscription
                ? ('subscription #' . $refundRequest->customer_subscription_id)
                : ('order ' . ($refundRequest->order?->order_number ?? ''));

            sendNotification(
                null,
                $customerId,
                $isApproved ? 'Refund Approved' : 'Refund Rejected',
                $isApproved
                    ? "Your refund request for {$reference} has been approved and added to your wallet."
                    : "Your refund request for {$reference} has been rejected.",
                'payment',
                [
                    'refund_request_id' => $refundRequest->id,
                    'order_id' => $refundRequest->order_id,
                    'invoice_id' => $refundRequest->invoice_id,
                    'customer_subscription_id' => $refundRequest->customer_subscription_id,
                    'amount' => $refundRequest->amount,
                    'status' => $status,
                ],
                $isApproved ? 'تمت الموافقة على الاسترجاع' : 'تم رفض طلب الاسترجاع',
                $isApproved
                    ? "تمت الموافقة على طلب الاسترجاع وتم إضافة المبلغ إلى محفظتك."
                    : "تم رفض طلب الاسترجاع."
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
