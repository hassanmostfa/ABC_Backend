<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\StorePaymentRequest;
use App\Http\Requests\Admin\UpdatePaymentRequest;
use App\Http\Resources\Admin\PaymentResource;
use App\Repositories\PaymentRepositoryInterface;
use App\Repositories\InvoiceRepositoryInterface;
use App\Repositories\OrderRepositoryInterface;
use App\Models\Payment;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PaymentController extends BaseApiController
{
    protected $paymentRepository;
    protected $invoiceRepository;
    protected $orderRepository;

    public function __construct(
        PaymentRepositoryInterface $paymentRepository,
        InvoiceRepositoryInterface $invoiceRepository,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->paymentRepository = $paymentRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->orderRepository = $orderRepository;
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
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Prepare filters
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'method' => $request->input('method'),
            'invoice_id' => $request->input('invoice_id'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
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

            // Get invoice to calculate amount if not provided
            $invoice = $this->invoiceRepository->findById($request->input('invoice_id'));
            if (!$invoice) {
                DB::rollBack();
                return $this->errorResponse('Invoice not found', 404);
            }

            // Check if invoice is already paid
            if ($invoice->status === 'paid') {
                DB::rollBack();
                return $this->errorResponse('This invoice is already paid. Cannot create additional payments.', 400);
            }

            // Calculate payment amount from invoice amount_due if not provided
            $paymentData = $request->validated();
            if (!isset($paymentData['amount']) || $paymentData['amount'] === null) {
                // Calculate remaining amount due (amount_due - sum of completed payments)
                $totalPaid = Payment::where('invoice_id', $invoice->id)
                    ->where('status', 'completed')
                    ->sum('amount');
                $remainingAmount = max(0, $invoice->amount_due - $totalPaid);
                $paymentData['amount'] = $remainingAmount;
            }

            // Validate wallet balance if payment method is wallet
            if (isset($paymentData['method']) && $paymentData['method'] === 'wallet') {
                // Load invoice with order and customer
                $invoice->load('order.customer');
                
                if (!$invoice->order || !$invoice->order->customer) {
                    DB::rollBack();
                    return $this->errorResponse('Invoice does not have an associated customer', 400);
                }

                $customer = $invoice->order->customer;
                $wallet = Wallet::where('customer_id', $customer->id)->first();

                if (!$wallet) {
                    DB::rollBack();
                    return $this->errorResponse('Customer wallet not found', 404);
                }

                // Check if wallet has enough balance
                if ($wallet->balance < $paymentData['amount']) {
                    DB::rollBack();
                    return $this->errorResponse(
                        'Insufficient wallet balance. Available: ' . number_format($wallet->balance, 2) . ', Required: ' . number_format($paymentData['amount'], 2),
                        400
                    );
                }
            }

            // Generate payment number
            $paymentNumber = $this->generatePaymentNumber();
            $paymentData['payment_number'] = $paymentNumber;

            // Set payment status to 'completed' by default if not provided
            if (!isset($paymentData['status'])) {
                $paymentData['status'] = 'completed';
            }

            // If status is 'completed', set paid_at timestamp
            if ($paymentData['status'] === 'completed') {
                $paymentData['paid_at'] = now();
            }

            $payment = $this->paymentRepository->create($paymentData);

            // Deduct amount from wallet if payment method is wallet and payment is completed
            if ($paymentData['status'] === 'completed' && isset($paymentData['method']) && $paymentData['method'] === 'wallet') {
                // Load invoice with order and customer if not already loaded
                if (!$invoice->relationLoaded('order')) {
                    $invoice->load('order.customer');
                }
                
                if ($invoice->order && $invoice->order->customer) {
                    $customer = $invoice->order->customer;
                    $wallet = Wallet::where('customer_id', $customer->id)->first();
                    
                    if ($wallet) {
                        // Deduct payment amount from wallet balance
                        $newBalance = max(0, $wallet->balance - $paymentData['amount']);
                        $wallet->update(['balance' => $newBalance]);
                    }
                }
            }

            // Update invoice and order if payment is completed and invoice is fully paid
            if ($paymentData['status'] === 'completed') {
                // Calculate total paid including this new payment
                $totalPaid = Payment::where('invoice_id', $invoice->id)
                    ->where('status', 'completed')
                    ->sum('amount');
                
                // If total paid equals or exceeds amount_due, mark invoice as paid and update order
                if ($totalPaid >= $invoice->amount_due) {
                    // Update invoice status and paid_at
                    $this->invoiceRepository->update($invoice->id, [
                        'paid_at' => now(),
                        'status' => 'paid',
                    ]);

                    // Update order status to 'completed' if invoice is fully paid
                    if ($invoice->order_id) {
                        $this->orderRepository->update($invoice->order_id, [
                            'status' => 'completed',
                        ]);
                    }
                }
            }

            DB::commit();

            // Reload with relationships
            $payment = $this->paymentRepository->findById($payment->id);
            $payment->load([
                'invoice.order.customer',
                'invoice.order.charity',
                'invoice.order.items.product',
                'invoice.order.items.variant',
            ]);

            // Log activity
            logAdminActivity('created', 'Payment', $payment->id);

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

            // Handle wallet balance changes when payment status changes
            $oldStatus = $payment->status;
            $paymentMethod = $updateData['method'] ?? $payment->method;
            
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

            // Handle wallet balance deduction/refund when payment method is wallet
            if ($paymentMethod === 'wallet') {
                $invoice = $this->invoiceRepository->findById($payment->invoice_id);
                if ($invoice) {
                    $invoice->load('order.customer');
                    
                    if ($invoice->order && $invoice->order->customer) {
                        $customer = $invoice->order->customer;
                        $wallet = Wallet::where('customer_id', $customer->id)->first();
                        
                        if ($wallet) {
                            // If payment status changed to 'completed', deduct amount
                            if (isset($updateData['status']) && $updateData['status'] === 'completed' && $oldStatus !== 'completed') {
                                $newBalance = max(0, $wallet->balance - $payment->amount);
                                $wallet->update(['balance' => $newBalance]);
                            }
                            // If payment status changed from 'completed' to something else, refund amount
                            elseif (isset($updateData['status']) && $updateData['status'] !== 'completed' && $oldStatus === 'completed') {
                                $newBalance = $wallet->balance + $payment->amount;
                                $wallet->update(['balance' => $newBalance]);
                            }
                        }
                    }
                }
            }

            // Update invoice and order if payment status is completed and invoice is fully paid
            if (isset($updateData['status']) && $updateData['status'] === 'completed') {
                $invoice = $this->invoiceRepository->findById($payment->invoice_id);
                if ($invoice) {
                    // Calculate total paid including this payment
                    $totalPaid = Payment::where('invoice_id', $invoice->id)
                        ->where('status', 'completed')
                        ->sum('amount');
                    
                    // If total paid equals or exceeds amount_due, mark invoice as paid and update order
                    if ($totalPaid >= $invoice->amount_due) {
                        // Update invoice status and paid_at
                        $this->invoiceRepository->update($invoice->id, [
                            'paid_at' => now(),
                            'status' => 'paid',
                        ]);

                        // Update order status to 'completed' if invoice is fully paid
                        if ($invoice->order_id) {
                            $this->orderRepository->update($invoice->order_id, [
                                'status' => 'completed',
                            ]);
                        }
                    }
                }
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

            // Log activity
            logAdminActivity('updated', 'Payment', $id);

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

        // Log activity
        logAdminActivity('deleted', 'Payment', $id);

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

