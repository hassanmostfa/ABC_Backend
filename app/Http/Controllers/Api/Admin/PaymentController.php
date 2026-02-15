<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\StorePaymentRequest;
use App\Http\Requests\Admin\UpdatePaymentRequest;
use App\Http\Resources\Admin\PaymentResource;
use App\Repositories\PaymentRepositoryInterface;
use App\Repositories\InvoiceRepositoryInterface;
use App\Repositories\OrderRepositoryInterface;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGatewayEvent;
use App\Models\Wallet;
use App\Services\UpaymentsService;
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
    protected $upaymentsService;

    public function __construct(
        PaymentRepositoryInterface $paymentRepository,
        InvoiceRepositoryInterface $invoiceRepository,
        OrderRepositoryInterface $orderRepository,
        WalletChargeService $walletChargeService,
        UpaymentsService $upaymentsService
    ) {
        $this->paymentRepository = $paymentRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->orderRepository = $orderRepository;
        $this->walletChargeService = $walletChargeService;
        $this->upaymentsService = $upaymentsService;
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
     * Handle payment success callback from Upayments.
     * Does not trust redirect params to update DB. If result=CAPTURED and track_id are present,
     * verifies via getPaymentStatus(track_id) and then updates Payment/Invoice (fallback when webhook is unreachable e.g. localhost).
     */
    public function success(Request $request): JsonResponse|View
    {
        $trackId = $request->query('track_id') ?? $request->input('track_id');
        $result = $request->query('result') ?? $request->input('result');

        if ($trackId && strtoupper((string) $result) === 'CAPTURED') {
            $requestedOrderId = $request->query('requested_order_id') ?? $request->input('requested_order_id');
            $receiptId = $request->query('receipt_id') ?? $request->input('receipt_id');
            $verifyViaApi = config('services.upayments.verify_via_status_api', true);

            $statusResult = null;
            if ($verifyViaApi) {
                try {
                    $statusResult = $this->upaymentsService->getPaymentStatus($trackId);
                } catch (\Exception $e) {
                    Log::warning('Upayments success callback: getPaymentStatus failed', [
                        'track_id' => $trackId,
                        'requested_order_id' => $requestedOrderId,
                        'message' => $e->getMessage(),
                    ]);
                }
            } else {
                if ($requestedOrderId) {
                    $orderForAmount = $this->orderRepository->findByOrderNumber($requestedOrderId);
                    if ($orderForAmount && !$orderForAmount->relationLoaded('invoice')) {
                        $orderForAmount->load('invoice');
                    }
                    $amount = $orderForAmount && $orderForAmount->invoice
                        ? (float) $orderForAmount->invoice->amount_due
                        : (float) $request->query('amount');
                    $statusResult = [
                        'gateway_status_raw' => 'CAPTURED',
                        'is_success' => true,
                        'is_failed' => false,
                        'amount' => $amount,
                        'currency' => 'KWD',
                        'track_id' => $trackId,
                        'receipt_id' => $receiptId ?: $request->query('receipt_id'),
                        'payment_id' => $request->query('payment_id'),
                        'tran_id' => $request->query('tran_id'),
                        'requested_order_id' => $requestedOrderId,
                    ];
                }
            }

            if ($statusResult !== null) {
                try {
                    $this->processVerifiedPayment($trackId, $statusResult, $receiptId, $requestedOrderId);
                } catch (\Exception $e) {
                    Log::warning('Upayments success callback: processVerifiedPayment failed', [
                        'track_id' => $trackId,
                        'requested_order_id' => $requestedOrderId,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

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
        $status = $invoiceStatus === 'paid' ? 'paid' : 'pending';

        if (!$request->expectsJson()) {
            return view('payment-success', ['order_number' => $order->order_number]);
        }
        return $this->successResponse([
            'success' => true,
            'status' => $status,
            'order_number' => $order->order_number,
            'invoice_status' => $invoiceStatus,
        ]);
    }

    /**
     * Handle payment cancellation callback from Upayments (UI-only: never create/update Payment or mark invoice paid).
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
     * Handle payment notification/webhook from Upayments.
     * Persists raw payload, verifies via getPaymentStatus(track_id), then updates Payment/Invoice only after verification.
     */
    public function notification(Request $request): JsonResponse
    {
        $payload = $request->all();
        $trackId = $request->input('track_id') ?? $request->input('data.track_id') ?? $payload['track_id'] ?? null;
        $receiptId = $request->input('receipt_id') ?? $request->input('data.receipt_id') ?? $payload['receipt_id'] ?? null;
        $requestedOrderId = $request->input('requested_order_id') ?? $request->input('data.requested_order_id') ?? $payload['requested_order_id'] ?? null;

        if (!$trackId) {
            Log::warning('Upayments webhook: missing track_id', ['requested_order_id' => $requestedOrderId]);
            return $this->errorResponse('Missing track_id', 400);
        }

        PaymentGatewayEvent::create([
            'provider' => 'upayments',
            'event_type' => 'webhook',
            'track_id' => $trackId,
            'receipt_id' => $receiptId,
            'payload' => $payload,
            'received_at' => now(),
        ]);

        try {
            $statusResult = $this->upaymentsService->getPaymentStatus($trackId);
        } catch (\Exception $e) {
            Log::warning('Upayments webhook: getPaymentStatus failed', [
                'track_id' => $trackId,
                'requested_order_id' => $requestedOrderId,
                'outcome' => 'verification_failed',
                'message' => $e->getMessage(),
            ]);
            return $this->errorResponse('Payment status verification failed', 502);
        }

        $orderNumber = $statusResult['requested_order_id'] ?? $requestedOrderId;
        $result = $this->processVerifiedPayment($trackId, $statusResult, $receiptId, $orderNumber);
        if (!$result['processed']) {
            Log::info('Upayments webhook: not processed', [
                'track_id' => $trackId,
                'requested_order_id' => $orderNumber,
                'outcome' => $result['reason'] ?? 'order_or_invoice_not_found',
            ]);
            return $this->successResponse(['message' => 'Webhook received'], 'Webhook received');
        }
        if ($result['idempotent'] ?? false) {
            return $this->successResponse(['message' => 'Webhook processed'], 'Webhook processed');
        }
        Log::info('Upayments webhook: processed', [
            'track_id' => $trackId,
            'requested_order_id' => $orderNumber,
            'outcome' => $result['payment_status'],
        ]);
        return $this->successResponse([
            'order_number' => $result['order']->order_number,
            'payment_status' => $result['payment_status'],
            'message' => 'Webhook processed successfully',
        ], 'Webhook processed successfully');
    }

    /**
     * Verify and apply payment status: updateOrCreate Payment by (gateway, track_id), mark invoice paid if completed.
     * Used by both webhook and success-callback fallback. Caller must have verified via getPaymentStatus first.
     *
     * @param string|null $fallbackOrderNumber Optional order number if status API does not return requested_order_id
     * @return array{processed: bool, idempotent?: bool, order?: \App\Models\Order, payment_status?: string, reason?: string}
     */
    protected function processVerifiedPayment(string $trackId, array $statusResult, ?string $receiptId = null, ?string $fallbackOrderNumber = null): array
    {
        $orderNumber = $statusResult['requested_order_id'] ?? $fallbackOrderNumber;
        $order = $orderNumber ? $this->orderRepository->findByOrderNumber($orderNumber) : null;
        if (!$order) {
            return ['processed' => false, 'reason' => 'order_not_found'];
        }
        $order->load('invoice');
        $invoice = $order->invoice;
        if (!$invoice) {
            return ['processed' => false, 'reason' => 'invoice_not_found'];
        }

        $gateway = 'upayments';
        $newStatus = $statusResult['is_success'] ? 'completed' : ($statusResult['is_failed'] ? 'failed' : 'pending');
        $amount = $statusResult['amount'] ?? (float) $invoice->amount_due;

        DB::beginTransaction();
        try {
            $payment = Payment::where('gateway', $gateway)->where('track_id', $trackId)->first();
            if ($payment) {
                if ($payment->status === 'completed') {
                    DB::commit();
                    return [
                        'processed' => true,
                        'idempotent' => true,
                        'order' => $order,
                        'payment_status' => 'completed',
                    ];
                }
                $payment->update([
                    'status' => $newStatus,
                    'amount' => $amount,
                    'tran_id' => $statusResult['tran_id'] ?? $payment->tran_id,
                    'payment_id' => $statusResult['payment_id'] ?? $payment->payment_id,
                    'receipt_id' => $statusResult['receipt_id'] ?? $payment->receipt_id ?? $receiptId,
                    'paid_at' => $newStatus === 'completed' ? now('Asia/Kuwait') : null,
                ]);
            } else {
                $payment = Payment::create([
                    'invoice_id' => $invoice->id,
                    'payment_number' => $this->generatePaymentNumber(),
                    'gateway' => $gateway,
                    'track_id' => $trackId,
                    'tran_id' => $statusResult['tran_id'] ?? null,
                    'payment_id' => $statusResult['payment_id'] ?? null,
                    'receipt_id' => $statusResult['receipt_id'] ?? $receiptId,
                    'amount' => $amount,
                    'method' => 'online',
                    'status' => $newStatus,
                    'paid_at' => $newStatus === 'completed' ? now('Asia/Kuwait') : null,
                ]);
            }

            if ($newStatus === 'completed') {
                $invoiceLocked = Invoice::where('id', $invoice->id)->lockForUpdate()->first();
                if ($invoiceLocked && $invoiceLocked->status !== 'paid') {
                    $totalPaid = (float) Payment::where('invoice_id', $invoice->id)->where('status', 'completed')->sum('amount');
                    if ($totalPaid >= (float) $invoiceLocked->amount_due) {
                        $invoiceLocked->update([
                            'paid_at' => now('Asia/Kuwait'),
                            'status' => 'paid',
                        ]);
                    }
                }
            }

            DB::commit();
            return [
                'processed' => true,
                'order' => $order->fresh(),
                'payment_status' => $newStatus,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Upayments processVerifiedPayment: exception', [
                'track_id' => $trackId,
                'requested_order_id' => $orderNumber,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Resolve order from callback params (success/cancel). Used only to read status; never trust to update DB.
     */
    protected function resolveOrderFromCallback(Request $request): ?\App\Models\Order
    {
        $orderNumber = $request->query('requested_order_id') ?? $request->input('requested_order_id');
        $orderIdParam = $request->query('order_id') ?? $request->input('order_id');
        $ref = $request->query('ref') ?? $request->input('ref');
        $trackId = $request->query('track_id') ?? $request->input('track_id');

        if ($orderNumber) {
            $order = $this->orderRepository->findByOrderNumber($orderNumber);
            if ($order) {
                return $order;
            }
        }
        if ($orderIdParam && is_numeric($orderIdParam) && (int) $orderIdParam > 0 && (int) $orderIdParam < 100000) {
            $order = $this->orderRepository->findById((int) $orderIdParam);
            if ($order) {
                return $order;
            }
        }
        if ($ref && is_numeric($ref) && (int) $ref > 0 && (int) $ref < 100000) {
            $order = $this->orderRepository->findById((int) $ref);
            if ($order) {
                return $order;
            }
        }
        if ($trackId) {
            $payment = Payment::where('gateway', 'upayments')->where('track_id', $trackId)->first();
            if ($payment && $payment->invoice_id) {
                $invoice = $this->invoiceRepository->findById($payment->invoice_id);
                if ($invoice && $invoice->order_id) {
                    return $this->orderRepository->findById($invoice->order_id);
                }
            }
        }
        return null;
    }

    /**
     * Handle wallet charge payment success callback from Upayments.
     * When opened in browser, returns HTML success page; otherwise JSON.
     */
    public function walletChargeSuccess(Request $request): JsonResponse|View
    {
        // Upayments may append params with ? instead of &, corrupting reference - use requested_order_id first (clean)
        $reference = $request->query('requested_order_id')
            ?? $this->extractWalletChargeReference($request->query('reference'));

        Log::info('Wallet charge success callback received', ['reference' => $reference, 'all_params' => $request->query()]);

        $payment = $this->walletChargeService->findByReference($reference ?? '');
        if (!$payment) {
            if (!$request->expectsJson()) {
                return view('payment-failed', ['order_number' => null]);
            }
            return $this->errorResponse('Wallet charge not found', 404);
        }

        $result = $request->query('result');
        if (strtoupper($result ?? '') === 'CAPTURED') {
            $this->walletChargeService->processSuccess($payment);
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
     * Handle wallet charge payment cancellation callback from Upayments.
     * When opened in browser, returns HTML failed page; otherwise JSON.
     */
    public function walletChargeCancel(Request $request): JsonResponse|View
    {
        $reference = $request->query('requested_order_id')
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
     * Handle wallet charge payment notification/webhook from Upayments
     */
    public function walletChargeNotification(Request $request): JsonResponse
    {
        $reference = $request->query('requested_order_id')
            ?? $request->input('requested_order_id')
            ?? $this->extractWalletChargeReference($request->query('reference'))
            ?? $this->extractWalletChargeReference($request->input('reference'))
            ?? $request->input('order.reference')
            ?? $request->input('order.id');

        Log::info('Wallet charge webhook received', ['reference' => $reference, 'data' => $request->all()]);

        $payment = $this->walletChargeService->findByReference($reference ?? '');
        if (!$payment) {
            return $this->errorResponse('Wallet charge not found', 404);
        }

        $paymentStatus = $request->input('result') ?? $request->input('status') ?? '';
        $statusLower = strtolower($paymentStatus);
        if (in_array($statusLower, ['captured', 'success', 'paid', 'completed', 'approved'])) {
            $this->walletChargeService->processSuccess($payment);
        }

        return $this->successResponse([
            'reference' => $payment->reference,
            'status' => $payment->fresh()->status,
            'message' => 'Webhook processed successfully',
        ], 'Webhook processed successfully');
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

