<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Admin\RefundRequestResource;
use App\Models\RefundRequest;
use App\Services\RefundRequestService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RefundRequestController extends BaseApiController
{
    public function __construct(
        protected RefundRequestService $refundRequestService
    ) {}

    /**
     * List refund requests with pagination
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|in:pending,approved,rejected',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = RefundRequest::with(['order', 'customer'])->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = $request->input('per_page', 15);
        $refundRequests = $query->paginate($perPage);

        return $this->paginatedResponse(
            $refundRequests->through(fn ($r) => new RefundRequestResource($r)),
            'Refund requests retrieved successfully'
        );
    }

    /**
     * Show single refund request
     */
    public function show(int $id): JsonResponse
    {
        $refundRequest = RefundRequest::with(['order', 'customer', 'invoice'])->find($id);

        if (!$refundRequest) {
            return $this->notFoundResponse('Refund request not found');
        }

        return $this->successResponse(new RefundRequestResource($refundRequest), 'Refund request retrieved');
    }

    /**
     * Approve refund request - add money to customer wallet
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        try {
            $result = $this->refundRequestService->approve($id, $request->input('admin_notes'));

            if (!$result['success']) {
                return $this->errorResponse($result['message'], 400);
            }

            logAdminActivity('approved', 'RefundRequest', $id);

            return $this->successResponse(
                new RefundRequestResource($result['refund_request']),
                $result['message']
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Reject refund request
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $result = $this->refundRequestService->reject($id, $request->input('admin_notes'));

        if (!$result['success']) {
            return $this->errorResponse($result['message'], 400);
        }

        logAdminActivity('rejected', 'RefundRequest', $id);

        return $this->successResponse(
            new RefundRequestResource($result['refund_request']),
            $result['message']
        );
    }
}
