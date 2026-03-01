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

            try {
                sendNotification(
                    null,
                    $refundRequest->customer_id,
                    'Refund Approved',
                    "Your refund request for order {$refundRequest->order?->order_number} has been approved and added to your wallet.",
                    'payment',
                    [
                        'refund_request_id' => $refundRequest->id,
                        'order_id' => $refundRequest->order_id,
                        'invoice_id' => $refundRequest->invoice_id,
                        'amount' => $refundRequest->amount,
                        'status' => RefundRequest::STATUS_APPROVED,
                    ],
                    'تمت الموافقة على الاسترجاع',
                    "تمت الموافقة على طلب الاسترجاع الخاص بالطلب {$refundRequest->order?->order_number} وتم إضافة المبلغ إلى محفظتك."
                );
            } catch (\Exception $e) {
                Log::warning('Failed to dispatch refund approval notification', [
                    'refund_request_id' => $refundRequest->id,
                    'message' => $e->getMessage(),
                ]);
            }

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

        try {
            $refundRequest->loadMissing('order');
            sendNotification(
                null,
                $refundRequest->customer_id,
                'Refund Rejected',
                "Your refund request for order {$refundRequest->order?->order_number} has been rejected.",
                'payment',
                [
                    'refund_request_id' => $refundRequest->id,
                    'order_id' => $refundRequest->order_id,
                    'invoice_id' => $refundRequest->invoice_id,
                    'amount' => $refundRequest->amount,
                    'status' => RefundRequest::STATUS_REJECTED,
                ],
                'تم رفض طلب الاسترجاع',
                "تم رفض طلب الاسترجاع الخاص بالطلب {$refundRequest->order?->order_number}."
            );
        } catch (\Exception $e) {
            Log::warning('Failed to dispatch refund rejection notification', [
                'refund_request_id' => $refundRequest->id,
                'message' => $e->getMessage(),
            ]);
        }

        return [
            'success' => true,
            'message' => 'Refund request rejected',
            'refund_request' => $refundRequest->fresh(),
        ];
    }
}
