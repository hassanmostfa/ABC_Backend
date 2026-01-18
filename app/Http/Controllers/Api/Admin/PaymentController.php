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
use Illuminate\Support\Facades\Log;

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

            // If status is 'completed', set paid_at timestamp in Kuwait timezone
            if ($paymentData['status'] === 'completed') {
                $paymentData['paid_at'] = now('Asia/Kuwait');
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
                
                // If total paid equals or exceeds amount_due, mark invoice as paid
                if ($totalPaid >= $invoice->amount_due) {
                    // Update invoice status and paid_at in Kuwait timezone
                    $this->invoiceRepository->update($invoice->id, [
                        'paid_at' => now('Asia/Kuwait'),
                        'status' => 'paid',
                    ]);
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
            
            // If status is being updated to 'completed', set paid_at timestamp in Kuwait timezone
            if (isset($updateData['status']) && $updateData['status'] === 'completed' && $payment->status !== 'completed') {
                $updateData['paid_at'] = now('Asia/Kuwait');
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
                    
                    // If total paid equals or exceeds amount_due, mark invoice as paid
                    if ($totalPaid >= $invoice->amount_due) {
                        // Update invoice status and paid_at in Kuwait timezone
                        $this->invoiceRepository->update($invoice->id, [
                            'paid_at' => now('Asia/Kuwait'),
                            'status' => 'paid',
                        ]);
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
     * Handle payment success callback from Upayments
     */
    public function success(Request $request): JsonResponse
    {
        // Upayments sends 'requested_order_id' which is the order number (e.g., "CALS-2026-000032")
        // They also send 'order_id' but it might be their transaction reference, not our database ID
        $orderNumber = $request->query('requested_order_id');
        $orderIdParam = $request->query('order_id');
        
        // Log all query parameters for debugging
        Log::info('Upayments success callback received', [
            'requested_order_id' => $orderNumber,
            'order_id' => $orderIdParam,
            'all_params' => $request->query()
        ]);

        $order = null;

        // Try to find order by order number first (this is what Upayments sends in 'requested_order_id')
        if ($orderNumber) {
            $order = $this->orderRepository->findByOrderNumber($orderNumber);
        }

        // If not found by order number, try order_id as database ID
        if (!$order && $orderIdParam) {
            // Handle case where order_id might be an array (duplicate query params)
            $orderId = is_array($orderIdParam) ? $orderIdParam[0] : $orderIdParam;
            
            // Only try as database ID if it's numeric and looks like a small integer
            if (is_numeric($orderId) && (int) $orderId == $orderId && (int) $orderId > 0 && (int) $orderId < 100000) {
                $order = $this->orderRepository->findById((int) $orderId);
            }
        }

        if (!$order) {
            Log::warning('Upayments success callback: Order not found', [
                'requested_order_id' => $orderNumber,
                'order_id' => $orderIdParam
            ]);
            return $this->errorResponse('Order not found', 404);
        }

        // Log successful order lookup
        Log::info('Upayments success callback: Order found', [
            'order_id' => $order->id,
            'order_number' => $order->order_number
        ]);

        // Process payment if result is CAPTURED (since webhook may not be reachable on localhost)
        $result = $request->query('result');
        if (strtoupper($result) === 'CAPTURED') {
            try {
                DB::beginTransaction();

                // Load invoice for this order
                if (!$order->relationLoaded('invoice')) {
                    $order->load('invoice');
                }

                $invoice = $order->invoice;
                if (!$invoice) {
                    DB::rollBack();
                    Log::warning('Upayments success callback: Invoice not found for order', [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number
                    ]);
                    return $this->errorResponse('Invoice not found for this order', 404);
                }

                // Extract payment amount
                $amount = $request->query('amount')
                    ?? $invoice->amount_due;

                // Extract payment metadata from URL query parameters
                // These values come from Upayments callback URL
                // Handle malformed URLs (with double ?) by parsing raw query string manually
                $allParams = $request->all();
                $fullUrl = $request->fullUrl();
                $rawQueryString = $request->getQueryString() ?? '';
                
                // Manually parse query string - handle malformed URLs with double ?
                // First, try to fix malformed query string (replace ? with & after first ?)
                $fixedQueryString = preg_replace('/\?/', '&', $rawQueryString, 1); // Replace only first ? after initial ?
                parse_str($fixedQueryString, $parsedQuery);
                
                // Also try extracting directly from URL using regex as fallback
                preg_match('/[?&]payment_id=([^&]*)/', $fullUrl, $paymentIdMatch);
                preg_match('/[?&]tran_id=([^&]*)/', $fullUrl, $tranIdMatch);
                
                // Try multiple sources to extract payment_id (malformed URL may put it in different places)
                $paymentId = $request->query('payment_id') 
                    ?? $request->input('payment_id') 
                    ?? ($parsedQuery['payment_id'] ?? null)
                    ?? ($paymentIdMatch[1] ?? null)
                    ?? ($allParams['payment_id'] ?? null);
                
                // Clean payment_id if it contains other parameters (due to malformed URL)
                if ($paymentId && strpos($paymentId, '&') !== false) {
                    $paymentId = explode('&', $paymentId)[0];
                }
                
                // For transaction_id, try tran_id, track_id, or ref as fallbacks
                $transactionId = $request->query('transaction_id') 
                    ?? $request->input('transaction_id')
                    ?? $request->query('tran_id')
                    ?? $request->input('tran_id')
                    ?? ($parsedQuery['tran_id'] ?? null)
                    ?? (!empty($tranIdMatch[1]) ? $tranIdMatch[1] : null)
                    ?? ($parsedQuery['track_id'] ?? null)  // track_id can be used as transaction_id
                    ?? ($parsedQuery['ref'] ?? null);      // ref can be used as transaction_id
                
                // Clean transaction_id if needed
                if ($transactionId && strpos($transactionId, '&') !== false) {
                    $transactionId = explode('&', $transactionId)[0];
                }
                
                $receiptId = $request->query('receipt_id') 
                    ?? $request->input('receipt_id') 
                    ?? ($parsedQuery['receipt_id'] ?? null)
                    ?? ($allParams['receipt_id'] ?? null);
                
                // Log extracted values for debugging
                Log::info('Upayments success callback: Extracted payment metadata', [
                    'payment_id' => $paymentId,
                    'transaction_id' => $transactionId,
                    'receipt_id' => $receiptId,
                    'raw_query_string' => $rawQueryString,
                    'regex_payment_id' => $paymentIdMatch[1] ?? 'not_found',
                    'parsed_query_payment_id' => $parsedQuery['payment_id'] ?? 'not_found',
                ]);
                
                // Check if payment already exists for this invoice (by receipt_id)
                $existingPayment = null;
                if ($receiptId) {
                    $existingPayment = Payment::where('invoice_id', $invoice->id)
                        ->where('receipt_id', $receiptId)
                        ->where('method', 'online')
                        ->latest()
                        ->first();
                }
                
                // If not found by receipt_id, find by invoice and method
                if (!$existingPayment) {
                    $existingPayment = Payment::where('invoice_id', $invoice->id)
                        ->where('method', 'online')
                        ->latest()
                        ->first();
                }

                // Prepare payment data (payment status is 'completed', invoice status is 'paid')
                // Note: Payment method should be 'online' (not 'online_link') as per payments table enum
                $paymentData = [
                    'invoice_id' => $invoice->id,
                    'amount' => (float) $amount,
                    'method' => 'online', // Valid enum values: cash, card, online, bank_transfer, wallet
                    'status' => 'completed', // Payment status is 'completed'
                    'paid_at' => now('Asia/Kuwait'),
                    'receipt_id' => $receiptId,
                ];

                // Create or update payment record
                if ($existingPayment && $existingPayment->status !== 'completed') {
                    // Update existing payment if it's now completed
                    $existingPayment = $this->paymentRepository->update($existingPayment->id, $paymentData);
                    Log::info('Upayments success callback: Payment updated', [
                        'payment_id' => $existingPayment->id,
                        'order_id' => $order->id,
                        'order_number' => $order->order_number
                    ]);
                } else if (!$existingPayment) {
                    // Create new payment record
                    $paymentData['payment_number'] = $this->generatePaymentNumber();
                    $existingPayment = $this->paymentRepository->create($paymentData);
                    Log::info('Upayments success callback: Payment created', [
                        'payment_id' => $existingPayment->id,
                        'order_id' => $order->id,
                        'order_number' => $order->order_number
                    ]);
                }

                // Calculate total paid including this payment
                $totalPaid = Payment::where('invoice_id', $invoice->id)
                    ->where('status', 'completed')
                    ->sum('amount');
                
                Log::info('Upayments success callback: Checking invoice payment status', [
                    'invoice_id' => $invoice->id,
                    'invoice_amount_due' => $invoice->amount_due,
                    'total_paid' => $totalPaid,
                    'will_update_invoice' => ($totalPaid >= $invoice->amount_due)
                ]);
                
                // If total paid equals or exceeds amount_due, mark invoice as paid
                if ($totalPaid >= $invoice->amount_due) {
                    // Update invoice status to 'paid' and set paid_at to now in Kuwait timezone
                    $updatedInvoice = $this->invoiceRepository->update($invoice->id, [
                        'paid_at' => now('Asia/Kuwait'),
                        'status' => 'paid', // Invoice status is 'paid'
                    ]);

                    Log::info('Upayments success callback: Invoice marked as paid', [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'invoice_id' => $invoice->id,
                        'total_paid' => $totalPaid,
                        'invoice_updated' => $updatedInvoice ? 'yes' : 'no'
                    ]);
                } else {
                    Log::warning('Upayments success callback: Invoice not fully paid yet', [
                        'invoice_id' => $invoice->id,
                        'invoice_amount_due' => $invoice->amount_due,
                        'total_paid' => $totalPaid,
                        'remaining' => $invoice->amount_due - $totalPaid
                    ]);
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Upayments success callback: Error processing payment', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'error' => $e->getMessage()
                ]);
                // Continue to return success response even if payment processing fails
            }
        }
        
        return $this->successResponse([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'message' => 'Payment successful'
        ], 'Payment processed successfully');
    }

    /**
     * Handle payment cancellation callback from Upayments
     */
    public function cancel(Request $request): JsonResponse
    {
        // Upayments sends 'requested_order_id' which is the order number (e.g., "CALS-2026-000032")
        // They also send 'order_id' but it might be their transaction reference, not our database ID
        $orderNumber = $request->query('requested_order_id');
        $orderIdParam = $request->query('order_id');
        
        // Log all query parameters for debugging
        Log::info('Upayments cancel callback received', [
            'requested_order_id' => $orderNumber,
            'order_id' => $orderIdParam,
            'all_params' => $request->query()
        ]);

        $order = null;

        // Try to find order by order number first (this is what Upayments sends in 'requested_order_id')
        if ($orderNumber) {
            $order = $this->orderRepository->findByOrderNumber($orderNumber);
        }

        // If not found by order number, try order_id as database ID
        if (!$order && $orderIdParam) {
            // Handle case where order_id might be an array (duplicate query params)
            $orderId = is_array($orderIdParam) ? $orderIdParam[0] : $orderIdParam;
            
            // Only try as database ID if it's numeric and looks like a small integer
            if (is_numeric($orderId) && (int) $orderId == $orderId && (int) $orderId > 0 && (int) $orderId < 100000) {
                $order = $this->orderRepository->findById((int) $orderId);
            }
        }

        if (!$order) {
            Log::warning('Upayments cancel callback: Order not found', [
                'requested_order_id' => $orderNumber,
                'order_id' => $orderIdParam
            ]);
            return $this->errorResponse('Order not found', 404);
        }

        // Log successful order lookup
        Log::info('Upayments cancel callback: Order found', [
            'order_id' => $order->id,
            'order_number' => $order->order_number
        ]);

        // Payment was cancelled - order status can remain as is
        
        return $this->successResponse([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'message' => 'Payment cancelled'
        ], 'Payment was cancelled');
    }

    /**
     * Handle payment notification/webhook from Upayments
     */
    public function notification(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Extract order number or ID from query param, request body, or webhook payload
            // Upayments sends 'requested_order_id' which is the order number (e.g., "CALS-2026-000032")
            $orderNumber = $request->query('requested_order_id')
                ?? $request->input('requested_order_id')
                ?? $request->input('order.reference')
                ?? $request->input('order.id');
            
            $orderIdParam = $request->query('order_id') 
                ?? $request->input('order_id')
                ?? $request->input('reference.id');
            
            $webhookData = $request->all();

            // Log the notification for debugging
            Log::info('Upayments webhook received', [
                'requested_order_id' => $orderNumber,
                'order_id' => $orderIdParam,
                'data' => $webhookData,
                'headers' => $request->headers->all()
            ]);

            $order = null;

            // Try to find order by order number first (this is what Upayments sends in 'requested_order_id' or 'order.reference')
            if ($orderNumber) {
                $order = $this->orderRepository->findByOrderNumber($orderNumber);
            }

            // If not found by order number, try order_id as database ID
            if (!$order && $orderIdParam) {
                // Handle case where order_id might be an array (duplicate query params)
                $orderId = is_array($orderIdParam) ? $orderIdParam[0] : $orderIdParam;
                
                // Only try as database ID if it's numeric and looks like a small integer
                if (is_numeric($orderId) && (int) $orderId == $orderId && (int) $orderId > 0 && (int) $orderId < 100000) {
                    $order = $this->orderRepository->findById((int) $orderId);
                }
            }

            if (!$order) {
                Log::warning('Upayments webhook: Order not found', [
                    'requested_order_id' => $orderNumber,
                    'order_id' => $orderIdParam,
                    'data' => $webhookData
                ]);
                return $this->errorResponse('Order not found', 404);
            }

            // Load invoice for this order
            if (!$order->relationLoaded('invoice')) {
                $order->load('invoice');
            }

            $invoice = $order->invoice;
            if (!$invoice) {
                Log::warning('Upayments webhook: Invoice not found for order', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number
                ]);
                return $this->errorResponse('Invoice not found for this order', 404);
            }

            // Extract payment status from webhook payload
            // Upayments sends 'result' field (e.g., 'CAPTURED') in callbacks
            $paymentStatus = $request->input('result') // Upayments uses 'result' in callbacks
                ?? $request->input('status')
                ?? $request->input('payment_status')
                ?? $request->input('transaction_status')
                ?? $request->input('data.status');

            // Extract payment amount
            $amount = $request->input('amount')
                ?? $request->input('order.amount')
                ?? $request->input('data.amount')
                ?? $invoice->amount_due;

            // Extract payment metadata from webhook payload
            $paymentId = $request->input('payment_id')
                ?? $request->input('data.payment_id');
            $transactionId = $request->input('transaction_id')
                ?? $request->input('tran_id')
                ?? $request->input('session_id')
                ?? $request->input('data.transaction_id')
                ?? $request->input('data.id');
            $receiptId = $request->input('receipt_id')
                ?? $request->input('data.receipt_id');

            // Map Upayments status to our payment status
            // Upayments typically uses: 'CAPTURED', 'SUCCESS', 'failed', 'pending', 'cancelled'
            $status = 'pending';
            $statusLower = strtolower($paymentStatus ?? '');
            if (in_array($statusLower, ['captured', 'success', 'paid', 'completed', 'approved'])) {
                $status = 'completed';
            } elseif (in_array($statusLower, ['failed', 'rejected', 'declined'])) {
                $status = 'failed';
            } elseif (in_array($statusLower, ['cancelled', 'canceled'])) {
                $status = 'failed'; // Treat cancelled as failed
            }

            // Log extracted values for debugging
            Log::info('Upayments webhook: Payment status extracted', [
                'payment_status_raw' => $paymentStatus,
                'payment_status_mapped' => $status,
                'amount' => $amount,
                'payment_id' => $paymentId,
                'transaction_id' => $transactionId,
                'receipt_id' => $receiptId,
                'invoice_id' => $invoice->id,
                'invoice_amount_due' => $invoice->amount_due
            ]);

            // Check if payment already exists for this invoice (by receipt_id)
            $existingPayment = null;
            if ($receiptId) {
                // Try to find payment by receipt ID
                $existingPayment = Payment::where('invoice_id', $invoice->id)
                    ->where('receipt_id', $receiptId)
                    ->where('method', 'online')
                    ->latest()
                    ->first();
            }
            
            // If not found by receipt_id, find by invoice and method
            if (!$existingPayment) {
                $existingPayment = Payment::where('invoice_id', $invoice->id)
                    ->where('method', 'online')
                    ->latest()
                    ->first();
            }

            // Prepare payment data
            // Note: Payment method should be 'online' (not 'online_link') as per payments table enum
            $paymentData = [
                'invoice_id' => $invoice->id,
                'amount' => (float) $amount,
                'method' => 'online', // Valid enum values: cash, card, online, bank_transfer, wallet
                'status' => $status,
                'receipt_id' => $receiptId,
            ];

            if ($status === 'completed') {
                $paymentData['paid_at'] = now('Asia/Kuwait');
            }

            // Create or update payment record
            if ($existingPayment && $status === 'completed' && $existingPayment->status !== 'completed') {
                // Update existing payment if it's now completed
                $existingPayment = $this->paymentRepository->update($existingPayment->id, $paymentData);
                Log::info('Upayments webhook: Payment updated', [
                    'payment_id' => $existingPayment->id,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $status
                ]);
            } else if (!$existingPayment) {
                // Create new payment record
                $paymentData['payment_number'] = $this->generatePaymentNumber();
                $existingPayment = $this->paymentRepository->create($paymentData);
                Log::info('Upayments webhook: Payment created', [
                    'payment_id' => $existingPayment->id,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $status
                ]);
            } else {
                Log::info('Upayments webhook: Payment already processed', [
                    'payment_id' => $existingPayment->id,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $status
                ]);
            }

            // Update invoice and order if payment is completed and invoice is fully paid
            if ($status === 'completed') {
                // Calculate total paid including this payment
                $totalPaid = Payment::where('invoice_id', $invoice->id)
                    ->where('status', 'completed')
                    ->sum('amount');
                
                Log::info('Upayments webhook: Checking invoice payment status', [
                    'invoice_id' => $invoice->id,
                    'invoice_amount_due' => $invoice->amount_due,
                    'total_paid' => $totalPaid,
                    'payment_status' => $status,
                    'will_update_invoice' => ($totalPaid >= $invoice->amount_due)
                ]);
                
                // If total paid equals or exceeds amount_due, mark invoice as paid
                if ($totalPaid >= $invoice->amount_due) {
                    // Update invoice status and paid_at in Kuwait timezone
                    $updatedInvoice = $this->invoiceRepository->update($invoice->id, [
                        'paid_at' => now('Asia/Kuwait'),
                        'status' => 'paid',
                    ]);

                    Log::info('Upayments webhook: Invoice marked as paid', [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'invoice_id' => $invoice->id,
                        'total_paid' => $totalPaid,
                        'invoice_updated' => $updatedInvoice ? 'yes' : 'no'
                    ]);
                } else {
                    Log::warning('Upayments webhook: Invoice not fully paid yet', [
                        'invoice_id' => $invoice->id,
                        'invoice_amount_due' => $invoice->amount_due,
                        'total_paid' => $totalPaid,
                        'remaining' => $invoice->amount_due - $totalPaid
                    ]);
                }
            } else {
                Log::info('Upayments webhook: Payment status is not completed', [
                    'payment_status' => $status,
                    'invoice_id' => $invoice->id
                ]);
            }

            DB::commit();

            return $this->successResponse([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_status' => $status,
                'message' => 'Webhook processed successfully'
            ], 'Webhook processed successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Upayments webhook error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->all()
            ]);
            
            return $this->errorResponse('Failed to process webhook: ' . $e->getMessage(), 500);
        }
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

