<?php

namespace App\Services;

use App\Models\RefundRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

        return [
            'success' => true,
            'message' => 'Refund request rejected',
            'refund_request' => $refundRequest->fresh(),
        ];
    }
}
