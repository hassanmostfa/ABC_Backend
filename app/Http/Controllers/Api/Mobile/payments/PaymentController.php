<?php

namespace App\Http\Controllers\Api\Mobile\payments;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Admin\PaymentResource;
use App\Repositories\PaymentRepositoryInterface;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PaymentController extends BaseApiController
{
    protected $paymentRepository;

    public function __construct(PaymentRepositoryInterface $paymentRepository)
    {
        $this->paymentRepository = $paymentRepository;
    }

    /**
     * Get all customer payments (mobile API)
     */
    public function index(Request $request): JsonResponse
    {
        // Validate filter parameters
        $request->validate([
            'status' => 'nullable|in:pending,completed,failed,refunded',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Get authenticated customer
        $customer = Auth::guard('sanctum')->user();

        if (!$customer) {
            return $this->unauthorizedResponse('No authenticated customer found');
        }

        // Get all payments: order payments (via invoice) + wallet charge payments (direct customer_id)
        $payments = Payment::where(function ($query) use ($customer) {
            $query->whereHas('invoice.order', fn ($q) => $q->where('customer_id', $customer->id))
                ->orWhere(function ($q) use ($customer) {
                    $q->where('customer_id', $customer->id)->where('type', 'wallet_charge');
                });
        })
        ->with(['invoice', 'invoice.order', 'invoice.order.customer', 'invoice.order.charity', 'invoice.order.items', 'customer'])
        ->orderBy('created_at', 'desc')
        ->get();

        // Apply status filter if provided
        if ($request->has('status')) {
            $payments = $payments->where('status', $request->input('status'));
        }

        // Apply pagination
        $perPage = $request->input('per_page', 15);
        $currentPage = $request->input('page', 1);
        $total = $payments->count();
        $offset = ($currentPage - 1) * $perPage;
        $paginatedPayments = $payments->slice($offset, $perPage)->values();

        // Transform payments using resource
        $transformedPayments = PaymentResource::collection($paginatedPayments);

        // Create response with pagination
        $response = [
            'success' => true,
            'message' => 'Payments retrieved successfully',
            'data' => $transformedPayments,
            'pagination' => [
                'current_page' => (int) $currentPage,
                'last_page' => (int) ceil($total / $perPage),
                'per_page' => (int) $perPage,
                'total' => $total,
                'from' => $total > 0 ? $offset + 1 : null,
                'to' => $total > 0 ? min($offset + $perPage, $total) : null,
            ],
        ];

        if ($request->has('status')) {
            $response['filters'] = ['status' => $request->input('status')];
        }

        return response()->json($response);
    }
}
