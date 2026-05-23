<?php

namespace App\Http\Controllers\Api\Admin;

use App\Jobs\DispatchErpOrderJob;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\StorePaymentRequest;
use App\Http\Requests\Admin\UpdatePaymentRequest;
use App\Http\Resources\Admin\PaymentResource;
use App\Repositories\PaymentRepositoryInterface;
use App\Repositories\InvoiceRepositoryInterface;
use App\Repositories\OrderRepositoryInterface;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderCheckout;
use App\Models\Payment;
use App\Models\PaymentGatewayEvent;
use App\Models\Wallet;
use App\Services\OttuPaymentProcessor;
use App\Services\OttuService;
use App\Services\WalletChargeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends BaseApiController
{
    protected $paymentRepository;
    protected $invoiceRepository;
    protected $orderRepository;
    protected $walletChargeService;
    protected $ottuService;
    protected $ottuPaymentProcessor;

    public function __construct(
        PaymentRepositoryInterface $paymentRepository,
        InvoiceRepositoryInterface $invoiceRepository,
        OrderRepositoryInterface $orderRepository,
        WalletChargeService $walletChargeService,
        OttuService $ottuService,
        OttuPaymentProcessor $ottuPaymentProcessor
    ) {
        $this->paymentRepository = $paymentRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->orderRepository = $orderRepository;
        $this->walletChargeService = $walletChargeService;
        $this->ottuService = $ottuService;
        $this->ottuPaymentProcessor = $ottuPaymentProcessor;
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

            // ERP: online_link orders when invoice becomes fully paid (e.g. admin-recorded payment)
            $invoiceAfter = $this->invoiceRepository->findById($invoice->id);
            if ($invoiceAfter && $invoiceAfter->status === 'paid') {
                $invoiceAfter->load('order');
                if ($invoiceAfter->order && $invoiceAfter->order->payment_method === 'online_link') {
                    DispatchErpOrderJob::dispatchAfterResponse($invoiceAfter->order->id);
                }
            }

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

            $paymentJustCompleted = isset($updateData['status']) && $updateData['status'] === 'completed' && $oldStatus !== 'completed';

            // ERP: online_link when this update marks payment completed and invoice is fully paid
            if ($paymentJustCompleted) {
                $paymentForInvoice = $this->paymentRepository->findById($id);
                if ($paymentForInvoice) {
                    $invoiceAfter = $this->invoiceRepository->findById($paymentForInvoice->invoice_id);
                    if ($invoiceAfter && $invoiceAfter->status === 'paid') {
                        $invoiceAfter->load('order');
                        if ($invoiceAfter->order && $invoiceAfter->order->payment_method === 'online_link') {
                            DispatchErpOrderJob::dispatchAfterResponse($invoiceAfter->order->id);
                        }
                    }
                }
            }

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
     * Handle payment success redirect from Ottu (browser callback).
     * Never trust redirect alone: verify HMAC when present, confirm with Ottu API, show success UI only if invoice is paid.
     */
    public function success(Request $request): JsonResponse|View
    {
        $sessionId = trim((string) ($request->query('session_id') ?? $request->input('session_id') ?? ''));
        $orderNo = $request->query('order_no') ?? $request->input('order_no');
        $redirectParams = $request->query();

        Log::info('Ottu success redirect received', [
            'session_id' => $sessionId,
            'order_no' => $orderNo,
            'has_signature' => array_key_exists('signature', $redirectParams),
        ]);

        if ($sessionId === '') {
            Log::warning('Ottu success redirect: missing session_id');

            return $this->renderPaymentCallbackResponse($request, null, false);
        }

        if (!$this->ottuService->verifyRedirectParams($redirectParams)) {
            Log::warning('Ottu success redirect: invalid HMAC signature (will still verify via Ottu API)', [
                'session_id' => $sessionId,
                'order_no' => $orderNo,
            ]);
        }

        $processedOrder = null;

        try {
            $statusResult = $this->ottuService->getPaymentStatusWithRetries($sessionId);
            if ($statusResult['is_success']) {
                $effectiveOrderNo = $statusResult['requested_order_id'] ?? $orderNo;
                $receiptId = is_array($redirectParams['pg_params'] ?? null)
                    ? ($redirectParams['pg_params']['receipt_no'] ?? null)
                    : null;
                $processResult = $this->ottuPaymentProcessor->processVerifiedPayment(
                    $sessionId,
                    $statusResult,
                    $receiptId,
                    $effectiveOrderNo
                );
                $processedOrder = $processResult['order'] ?? null;
            } else {
                Log::info('Ottu success redirect: payment not successful at gateway', [
                    'session_id' => $sessionId,
                    'order_no' => $orderNo,
                    'gateway_status_raw' => $statusResult['gateway_status_raw'] ?? null,
                    'is_failed' => $statusResult['is_failed'] ?? false,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Ottu success redirect: payment verification failed', [
                'session_id' => $sessionId,
                'order_no' => $orderNo,
                'message' => $e->getMessage(),
            ]);
        }

        $order = $processedOrder ?? $this->resolveOrderFromCallback($request);
        if (!$order) {
            if ($request->expectsJson()) {
                return $this->errorResponse('Order not found', 404);
            }

            return $this->renderPaymentCallbackResponse($request, null, false);
        }

        $order->load('invoice');
        $isPaid = $order->invoice && $order->invoice->status === 'paid';

        return $this->renderPaymentCallbackResponse($request, $order, $isPaid);
    }

    /**
     * Render payment redirect UI/JSON from invoice state (webhook + API are source of truth).
     */
    protected function renderPaymentCallbackResponse(Request $request, ?\App\Models\Order $order, bool $showSuccess): JsonResponse|View
    {
        $orderNumber = $order?->order_number;
        $invoiceStatus = $order?->invoice?->status ?? 'pending';

        if (!$request->expectsJson()) {
            return $showSuccess
                ? view('payment-success', ['order_number' => $orderNumber])
                : view('payment-failed', ['order_number' => $orderNumber]);
        }

        return response()->json([
            'success' => $showSuccess,
            'status' => $showSuccess ? 'paid' : 'pending',
            'order_number' => $orderNumber,
            'invoice_status' => $invoiceStatus,
            'message' => $showSuccess
                ? 'Payment completed successfully.'
                : 'Payment not confirmed. Please wait or contact support if you were charged.',
        ], $showSuccess ? 200 : 200);
    }

    /**
     * Handle payment cancellation callback from Ottu (UI-only: never create/update Payment or mark invoice paid).
     * When opened in browser (no JSON Accept), returns HTML failed page; otherwise JSON.
     */
    public function cancel(Request $request): JsonResponse|View
    {
        $order = $this->resolveOrderFromCallback($request);
        if (!$order) {
            if (!$request->expectsJson()) {
                return view('payment-failed', ['order_number' => null]);
            }
            return $this->errorResponse('Order not found', 404);
        }
        $order->load('invoice');
        $invoice = $order->invoice;
        $invoiceStatus = $invoice ? $invoice->status : 'pending';

        if (!$request->expectsJson()) {
            return view('payment-failed', ['order_number' => $order->order_number]);
        }
        return $this->successResponse([
            'success' => true,
            'status' => 'failed',
            'order_number' => $order->order_number,
            'invoice_status' => $invoiceStatus,
        ]);
    }

    /**
     * Handle payment notification/webhook from Ottu.
     * Verifies HMAC signature, persists raw payload, verifies via getPaymentStatus, then updates Payment/Invoice.
     * Must return HTTP 200 for Ottu to redirect the customer to redirect_url.
     */
    public function notification(Request $request): JsonResponse
    {
        $payload = $request->all();
        $sessionId = $payload['session_id'] ?? null;
        $orderNo = $payload['order_no'] ?? null;
        $result = $payload['result'] ?? null;
        $state = $payload['state'] ?? null;

        if (!$sessionId) {
            Log::warning('Ottu webhook: missing session_id', ['order_no' => $orderNo]);
            return $this->errorResponse('Missing session_id', 400);
        }

        if (!$this->ottuService->verifySignature($payload)) {
            Log::warning('Ottu webhook: signature verification failed', [
                'session_id' => $sessionId,
                'order_no' => $orderNo,
            ]);
            return $this->errorResponse('Invalid signature', 403);
        }

        $pgParams = $payload['pg_params'] ?? [];

        PaymentGatewayEvent::create([
            'provider' => 'ottu',
            'event_type' => 'webhook',
            'track_id' => $sessionId,
            'receipt_id' => $pgParams['receipt_no'] ?? null,
            'payload' => $payload,
            'received_at' => now(),
        ]);

        $statusResult = $this->ottuService->buildStatusResultFromWebhook($payload, $sessionId);
        if ($statusResult['requested_order_id'] === null && $orderNo !== null) {
            $statusResult['requested_order_id'] = $orderNo;
        }

        try {
            $processResult = $this->ottuPaymentProcessor->processVerifiedPayment(
                $sessionId,
                $statusResult,
                $pgParams['receipt_no'] ?? null,
                $orderNo
            );
        } catch (\Exception $e) {
            Log::error('Ottu webhook: processVerifiedPayment exception', [
                'session_id' => $sessionId,
                'order_no' => $orderNo,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Webhook received'], 200);
        }

        if (!$processResult['processed']) {
            Log::info('Ottu webhook: not processed', [
                'session_id' => $sessionId,
                'order_no' => $orderNo,
                'outcome' => $processResult['reason'] ?? 'order_or_invoice_not_found',
            ]);

            return response()->json(['message' => 'Webhook received'], 200);
        }
        if ($processResult['idempotent'] ?? false) {
            return response()->json(['message' => 'Webhook processed'], 200);
        }
        Log::info('Ottu webhook: processed', [
            'session_id' => $sessionId,
            'order_no' => $orderNo,
            'outcome' => $processResult['payment_status'],
        ]);

        return response()->json([
            'order_number' => $processResult['order']->order_number,
            'payment_status' => $processResult['payment_status'],
            'message' => 'Webhook processed successfully',
        ], 200);
    }

    /**
     * Resolve order from callback params (success/cancel). Used only to read status; never trust to update DB.
     */
    protected function resolveOrderFromCallback(Request $request): ?\App\Models\Order
    {
        $orderNumber = $request->query('order_no') ?? $request->input('order_no')
            ?? $request->query('requested_order_id') ?? $request->input('requested_order_id');
        $orderIdParam = $request->query('order_id') ?? $request->input('order_id');
        $sessionId = $request->query('session_id') ?? $request->input('session_id');

        if ($orderNumber) {
            $order = $this->orderRepository->findByOrderNumber($orderNumber);
            if ($order) {
                return $order;
            }

            $checkout = OrderCheckout::query()
                ->where('order_number', $orderNumber)
                ->whereNotNull('order_id')
                ->first();
            if ($checkout?->order_id) {
                return $this->orderRepository->findById((int) $checkout->order_id);
            }
        }
        if ($orderIdParam && is_numeric($orderIdParam) && (int) $orderIdParam > 0 && (int) $orderIdParam < 100000) {
            $order = $this->orderRepository->findById((int) $orderIdParam);
            if ($order) {
                return $order;
            }

            $checkout = OrderCheckout::query()->find((int) $orderIdParam);
            if ($checkout?->order_id) {
                return $this->orderRepository->findById((int) $checkout->order_id);
            }
        }
        if ($sessionId) {
            $payment = Payment::where('gateway', 'ottu')->where('track_id', $sessionId)->first();
            if ($payment) {
                if ($payment->order_checkout_id) {
                    $checkout = OrderCheckout::query()->find($payment->order_checkout_id);
                    if ($checkout?->order_id) {
                        return $this->orderRepository->findById((int) $checkout->order_id);
                    }
                }

                if ($payment->invoice_id) {
                    $invoice = $this->invoiceRepository->findById($payment->invoice_id);
                    if ($invoice && $invoice->order_id) {
                        return $this->orderRepository->findById($invoice->order_id);
                    }
                }
            }
        }
        return null;
    }

    /**
     * Handle wallet charge payment success callback from Ottu.
     * When opened in browser, returns HTML success page; otherwise JSON.
     * SECURITY: Requires session_id and verifies payment status before crediting wallet.
     */
    public function walletChargeSuccess(Request $request): JsonResponse|View
    {
        $reference = $request->query('order_no')
            ?? $request->query('requested_order_id')
            ?? $this->extractWalletChargeReference($request->query('reference'));

        $sessionId = $request->query('session_id');

        Log::info('Wallet charge success callback received', ['reference' => $reference, 'session_id' => $sessionId, 'all_params' => $request->query()]);

        // SECURITY: Require session_id to prevent unauthorized wallet top-ups
        if (!$sessionId) {
            Log::warning('Wallet charge success: missing session_id - rejecting request', ['reference' => $reference]);
            if (!$request->expectsJson()) {
                return view('payment-failed', ['order_number' => null]);
            }
            return $this->errorResponse('Payment verification failed: missing session_id', 400);
        }

        $payment = $this->walletChargeService->findByReference($reference ?? '');
        if (!$payment) {
            if (!$request->expectsJson()) {
                return view('payment-failed', ['order_number' => null]);
            }
            return $this->errorResponse('Wallet charge not found', 404);
        }

        // SECURITY: Always verify payment status with gateway before crediting wallet
        try {
            $statusResult = $this->ottuService->getPaymentStatus($sessionId);
            if ($statusResult['is_success']) {
                $this->walletChargeService->processSuccess($payment);
            } else {
                Log::warning('Wallet charge success: payment not successful according to gateway', [
                    'session_id' => $sessionId,
                    'reference' => $reference,
                    'gateway_status' => $statusResult['gateway_status_raw'] ?? null,
                ]);
                if (!$request->expectsJson()) {
                    return view('payment-failed', ['order_number' => $payment->reference]);
                }
                return $this->errorResponse('Payment was not successful', 400);
            }
        } catch (\Exception $e) {
            Log::error('Wallet charge success: payment verification failed - NOT crediting wallet', [
                'session_id' => $sessionId,
                'reference' => $reference,
                'message' => $e->getMessage(),
            ]);
            if (!$request->expectsJson()) {
                return view('payment-failed', ['order_number' => $payment->reference]);
            }
            return $this->errorResponse('Payment verification failed: ' . $e->getMessage(), 500);
        }

        if (!$request->expectsJson()) {
            return view('payment-success', ['order_number' => $payment->reference]);
        }
        return $this->successResponse([
            'reference' => $payment->reference,
            'status' => $payment->fresh()->status,
            'message' => 'Wallet charge processed successfully',
        ], 'Payment processed successfully');
    }

    /**
     * Handle wallet charge payment cancellation callback from Ottu.
     * When opened in browser, returns HTML failed page; otherwise JSON.
     */
    public function walletChargeCancel(Request $request): JsonResponse|View
    {
        $reference = $request->query('order_no')
            ?? $request->query('requested_order_id')
            ?? $this->extractWalletChargeReference($request->query('reference'));

        Log::info('Wallet charge cancel callback received', ['reference' => $reference]);

        $payment = $this->walletChargeService->findByReference($reference ?? '');
        if ($payment) {
            $this->walletChargeService->processCancel($payment);
        }

        if (!$request->expectsJson()) {
            return view('payment-failed', ['order_number' => $reference]);
        }
        return $this->successResponse([
            'reference' => $reference,
            'message' => 'Wallet charge was cancelled',
        ], 'Payment was cancelled');
    }

    /**
     * Handle wallet charge payment notification/webhook from Ottu.
     * Verifies HMAC signature and processes wallet charge based on result/state.
     */
    public function walletChargeNotification(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (!$this->ottuService->verifySignature($payload)) {
            Log::warning('Ottu wallet charge webhook: signature verification failed', [
                'order_no' => $payload['order_no'] ?? null,
            ]);
            return $this->errorResponse('Invalid signature', 403);
        }

        $reference = $payload['order_no']
            ?? $request->input('requested_order_id')
            ?? $this->extractWalletChargeReference($request->query('reference'))
            ?? $this->extractWalletChargeReference($request->input('reference'));

        Log::info('Wallet charge webhook received', ['reference' => $reference, 'data' => $payload]);

        $payment = $this->walletChargeService->findByReference($reference ?? '');
        if (!$payment) {
            return $this->errorResponse('Wallet charge not found', 404);
        }

        if ($this->ottuService->isSuccessfulPayment($payload['result'] ?? null, $payload['state'] ?? null, $payload)) {
            $this->walletChargeService->processSuccess($payment);
        }

        return response()->json([
            'reference' => $payment->reference,
            'status' => $payment->fresh()->status,
            'message' => 'Webhook processed successfully',
        ], 200);
    }

    /**
     * Extract clean wallet charge reference (WCH-YYYY-NNNNNN) from param that may have ? or & appended by gateway
     */
    protected function extractWalletChargeReference(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        $cleaned = preg_split('/[?&]/', $value)[0] ?? $value;
        return trim($cleaned) ?: null;
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

