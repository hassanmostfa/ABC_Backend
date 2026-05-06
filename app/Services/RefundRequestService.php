<?php

namespace App\Services;

use App\Models\RefundRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RefundRequestService
{
    public function __construct(
        protected WalletService $walletService,
        protected InvoiceService $invoiceService
    ) {}

    /**
     * Approve refund request - add money to customer wallet
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

            DB::commit();

            $this->notifyCustomerRefundStatus($refundRequest, RefundRequest::STATUS_APPROVED);

            return [
                'success' => true,
                'message' => 'Refund approved. Money has been added to customer wallet.',
                'refund_request' => $refundRequest->fresh(),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Reject refund request
     */
    public function reject(int $refundRequestId, ?string $adminNotes = null): array
    {
        $refundRequest = RefundRequest::find($refundRequestId);
        if (!$refundRequest) {
            return ['success' => false, 'message' => 'Refund request not found'];
        }

        if ($refundRequest->status !== RefundRequest::STATUS_PENDING) {
            return ['success' => false, 'message' => 'Refund request is already processed'];
        }

        $refundRequest->update([
            'status' => RefundRequest::STATUS_REJECTED,
            'admin_notes' => $adminNotes ?? $refundRequest->admin_notes,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        $this->notifyCustomerRefundStatus($refundRequest, RefundRequest::STATUS_REJECTED);

        return [
            'success' => true,
            'message' => 'Refund request rejected',
            'refund_request' => $refundRequest->fresh(),
        ];
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
