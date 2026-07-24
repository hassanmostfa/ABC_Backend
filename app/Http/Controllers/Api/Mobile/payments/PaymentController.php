<?php

namespace App\Http\Controllers\Api\Mobile\payments;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Admin\PaymentResource;
use App\Repositories\Payments\PaymentRepositoryInterface;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
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
            'status' => 'nullable|in:pending,completed,failed,refunded,cancelled',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Get authenticated customer
        $customer = Auth::guard('sanctum')->user();

        if (!$customer) {
            return $this->unauthorizedResponse('No authenticated customer found');
        }

        // Get all payments: order payments (via invoice) + wallet charge payments (direct customer_id)
        $payments = Payment::query()
        ->where(function (Builder $query) use ($customer): void {
            $query->whereHas('invoice', function (Builder $invoiceQuery) use ($customer): void {
                $invoiceQuery->whereHas('order', function (Builder $orderQuery) use ($customer): void {
                    $orderQuery->where('customer_id', $customer->id);
                });
            })
            ->orWhere(function (Builder $walletQuery) use ($customer): void {
                $walletQuery->where('customer_id', $customer->id)
                    ->where('type', Payment::TYPE_WALLET_CHARGE);
            });
        })
        ->with([
            'invoice',
            'invoice.order',
            'invoice.order.customer',
            'invoice.order.charity',
            'invoice.order.items',
            'invoice.order.customerAddress',
            'customer',
            'creator',
            'orderCheckout',
            'orderCheckout.customer',
            'orderCheckout.order',
            'orderCheckout.order.customer',
            'orderCheckout.order.charity',
            'orderCheckout.order.items',
            'orderCheckout.order.customerAddress',
            'orderCheckout.order.invoice',
        ])
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
