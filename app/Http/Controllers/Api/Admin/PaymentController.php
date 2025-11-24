<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\StorePaymentRequest;
use App\Http\Requests\Admin\UpdatePaymentRequest;
use App\Http\Resources\Admin\PaymentResource;
use App\Repositories\PaymentRepositoryInterface;
use App\Repositories\InvoiceRepositoryInterface;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PaymentController extends BaseApiController
{
    protected $paymentRepository;
    protected $invoiceRepository;

    public function __construct(
        PaymentRepositoryInterface $paymentRepository,
        InvoiceRepositoryInterface $invoiceRepository
    ) {
        $this->paymentRepository = $paymentRepository;
        $this->invoiceRepository = $invoiceRepository;
    }

    /**
     * Display a listing of payments.
     */
    public function index(Request $request): JsonResponse
    {
        // Validate filter parameters
        $request->validate([
            'search' => 'nullable|string|max:1000',
            'status' => 'nullable|in:pending,completed,failed,refunded',
            'method' => 'nullable|in:cash,card,online,bank_transfer,wallet',
            'invoice_id' => 'nullable|integer|exists:invoices,id',
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'paid_from' => 'nullable|date',
            'paid_to' => 'nullable|date|after_or_equal:paid_from',
            'sort_by' => 'nullable|in:payment_number,amount,method,status,paid_at,created_at,updated_at',
            'sort_order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Prepare filters
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'method' => $request->input('method'),
            'invoice_id' => $request->input('invoice_id'),
            'min_amount' => $request->input('min_amount'),
            'max_amount' => $request->input('max_amount'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'paid_from' => $request->input('paid_from'),
            'paid_to' => $request->input('paid_to'),
            'sort_by' => $request->input('sort_by', 'created_at'),
            'sort_order' => $request->input('sort_order', 'desc'),
        ];

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        $perPage = $request->input('per_page', 15);
        $payments = $this->paymentRepository->getAllPaginated($filters, $perPage);

        // Transform payments using resource
        $transformedPayments = PaymentResource::collection($payments->items());

        // Create a custom response with pagination and filters
        $response = [
            'success' => true,
            'message' => 'Payments retrieved successfully',
            'data' => $transformedPayments,
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'from' => $payments->firstItem(),
                'to' => $payments->lastItem(),
            ]
        ];

        if (!empty($filters)) {
            $response['filters'] = $filters;
        }

        return response()->json($response);
    }

    /**
     * Store a newly created payment in storage.
     */
    public function store(StorePaymentRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Generate payment number
            $paymentNumber = $this->generatePaymentNumber();

            // Create payment data
            $paymentData = $request->validated();
            $paymentData['payment_number'] = $paymentNumber;

            // If status is 'completed', set paid_at timestamp
            if (isset($paymentData['status']) && $paymentData['status'] === 'completed') {
                $paymentData['paid_at'] = now();
            }

            $payment = $this->paymentRepository->create($paymentData);

            DB::commit();

            // Reload with relationships
            $payment = $this->paymentRepository->findById($payment->id);
            $payment->load([
                'invoice.order.customer',
                'invoice.order.charity',
                'invoice.order.items.product',
                'invoice.order.items.variant',
            ]);

            return $this->createdResponse(new PaymentResource($payment), 'Payment created successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to create payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified payment.
     */
    public function show(int $id): JsonResponse
    {
        $payment = $this->paymentRepository->findById($id);

        if (!$payment) {
            return $this->notFoundResponse('Payment not found');
        }

        // Load all relationships
        $payment->load([
            'invoice.order.customer',
            'invoice.order.charity',
            'invoice.order.offer',
            'invoice.order.items.product',
            'invoice.order.items.variant',
            'invoice.order.delivery',
        ]);

        return $this->resourceResponse(new PaymentResource($payment), 'Payment retrieved successfully');
    }

    /**
     * Update the specified payment in storage.
     */
    public function update(UpdatePaymentRequest $request, int $id): JsonResponse
    {
        $payment = $this->paymentRepository->findById($id);

        if (!$payment) {
            return $this->notFoundResponse('Payment not found');
        }

        try {
            DB::beginTransaction();

            $updateData = $request->validated();

            // If status is being updated to 'completed', set paid_at timestamp
            if (isset($updateData['status']) && $updateData['status'] === 'completed' && $payment->status !== 'completed') {
                $updateData['paid_at'] = now();
            } elseif (isset($updateData['status']) && $updateData['status'] !== 'completed' && $payment->status === 'completed') {
                // If status is changed from 'completed' to something else, clear paid_at
                $updateData['paid_at'] = null;
            }

            $payment = $this->paymentRepository->update($id, $updateData);

            if (!$payment) {
                DB::rollBack();
                return $this->errorResponse('Failed to update payment', 500);
            }

            DB::commit();

            // Reload with relationships
            $payment = $this->paymentRepository->findById($id);
            $payment->load([
                'invoice.order.customer',
                'invoice.order.charity',
                'invoice.order.offer',
                'invoice.order.items.product',
                'invoice.order.items.variant',
                'invoice.order.delivery',
            ]);

            return $this->updatedResponse(new PaymentResource($payment), 'Payment updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to update payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified payment from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->paymentRepository->delete($id);

        if (!$deleted) {
            return $this->notFoundResponse('Payment not found');
        }

        return $this->deletedResponse('Payment deleted successfully');
    }

    /**
     * Generate unique payment number
     *
     * @return string
     */
    protected function generatePaymentNumber(): string
    {
        $year = date('Y');
        $pattern = 'PAY-' . $year . '-%';
        
        // Find the last payment number with this pattern
        $lastPayment = Payment::where('payment_number', 'LIKE', $pattern)
            ->orderBy('payment_number', 'desc')
            ->first();
        
        // Extract sequence number from last payment
        $sequence = 1;
        if ($lastPayment) {
            // Extract the sequence part (last 6 digits after the year)
            $parts = explode('-', $lastPayment->payment_number);
            if (count($parts) === 3 && isset($parts[2])) {
                $sequence = (int) $parts[2] + 1;
            }
        }
        
        // Format: PAY-YEAR-000001 (6 digits with leading zeros)
        return sprintf('PAY-%s-%06d', $year, $sequence);
    }
}

