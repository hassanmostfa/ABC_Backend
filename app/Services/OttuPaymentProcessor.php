<?php

namespace App\Services;

use App\Jobs\DispatchErpOrderJob;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderCheckout;
use App\Models\Payment;
use App\Repositories\OrderRepositoryInterface;
use App\Support\PaymentCreatorResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OttuPaymentProcessor
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepository,
        protected OttuService $ottuService,
    ) {}

    /**
     * Poll Ottu and apply payment + invoice updates for an order (use when webhook cannot reach the server).
     *
     * @return array{success: bool, message: string, invoice_status?: string, payment_status?: string, gateway_status_raw?: mixed}
     */
    public function syncOrderPayment(Order $order, ?string $sessionIdOverride = null): array
    {
        $order->load(['invoice.payments']);

        if ($order->payment_method !== 'online_link') {
            return ['success' => false, 'message' => 'Order is not an online payment order.'];
        }

        $invoice = $order->invoice;
        if (!$invoice) {
            return ['success' => false, 'message' => 'Order has no invoice.'];
        }

        if ($invoice->status === 'paid') {
            return [
                'success' => true,
                'message' => 'Invoice is already paid.',
                'invoice_status' => 'paid',
                'payment_status' => 'completed',
            ];
        }

        $sessionId = $sessionIdOverride ?? $this->resolveSessionIdForOrder($order);
        if ($sessionId === null || $sessionId === '') {
            return ['success' => false, 'message' => 'No Ottu session found for this order. Regenerate the payment link and try again.'];
        }

        try {
            $statusResult = $this->ottuService->getPaymentStatusWithRetries($sessionId);
        } catch (\Exception $e) {
            Log::warning('Ottu syncOrderPayment: getPaymentStatus failed', [
                'order_id' => $order->id,
                'session_id' => $sessionId,
                'message' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Could not verify payment with Ottu: ' . $e->getMessage()];
        }

        if (!$statusResult['is_success']) {
            return [
                'success' => false,
                'message' => ($statusResult['is_failed'] ?? false)
                    ? 'Payment was not completed. You can retry using the same payment link.'
                    : 'Payment is not completed at Ottu yet.',
                'gateway_status_raw' => $statusResult['gateway_status_raw'] ?? null,
                'invoice_status' => $invoice->status,
                'payment_status' => $this->resolvePaymentStatus($statusResult),
            ];
        }

        try {
            $processResult = $this->processVerifiedPayment(
                $sessionId,
                $statusResult,
                null,
                $order->order_number
            );
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Failed to record payment: ' . $e->getMessage()];
        }

        if (!$processResult['processed']) {
            return [
                'success' => false,
                'message' => 'Payment verified at Ottu but could not be applied: ' . ($processResult['reason'] ?? 'unknown'),
            ];
        }

        $order->refresh();
        $order->load('invoice');

        return [
            'success' => true,
            'message' => 'Payment synced successfully.',
            'invoice_status' => $order->invoice?->status ?? 'pending',
            'payment_status' => $processResult['payment_status'] ?? 'completed',
            'gateway_status_raw' => $statusResult['gateway_status_raw'] ?? null,
        ];
    }

    public function resolveSessionIdForOrder(Order $order): ?string
    {
        $invoice = $order->invoice;
        if (!$invoice) {
            return null;
        }

        if (!$order->relationLoaded('payments')) {
            $order->load('invoice.payments');
        }

        $pending = Payment::query()
            ->where('gateway', 'ottu')
            ->where('invoice_id', $invoice->id)
            ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_FAILED])
            ->whereNotNull('track_id')
            ->orderByDesc('id')
            ->first();

        if ($pending?->track_id) {
            if ($pending->status === Payment::STATUS_FAILED) {
                $pending->update(['status' => Payment::STATUS_PENDING, 'paid_at' => null]);
            }

            return (string) $pending->track_id;
        }

        $completed = Payment::query()
            ->where('gateway', 'ottu')
            ->where('invoice_id', $invoice->id)
            ->whereNotNull('track_id')
            ->orderByDesc('id')
            ->first();

        if ($completed?->track_id) {
            return (string) $completed->track_id;
        }

        if (is_string($invoice->payment_link) && $invoice->payment_link !== '') {
            return $this->ottuService->extractSessionIdFromUrl($invoice->payment_link);
        }

        return null;
    }

    /**
     * @return array{processed: bool, idempotent?: bool, order?: Order, payment_status?: string, reason?: string}
     */
    public function processVerifiedCheckoutPayment(string $trackId, array $statusResult): array
    {
        $payment = Payment::query()
            ->where('gateway', 'ottu')
            ->where('track_id', $trackId)
            ->first();

        if (!$payment || $payment->type !== Payment::TYPE_ORDER_CHECKOUT) {
            $checkout = null;
            if ($payment?->order_checkout_id) {
                $checkout = OrderCheckout::query()->find($payment->order_checkout_id);
            } else {
                $orderNumber = $statusResult['requested_order_id'] ?? null;
                if ($orderNumber) {
                    $checkout = OrderCheckout::query()->where('order_number', $orderNumber)->first();
                }
            }

            if (!$checkout) {
                return ['processed' => false, 'reason' => 'checkout_not_found'];
            }

            if (!$payment) {
                $payment = Payment::firstOrCreate(
                    ['gateway' => 'ottu', 'track_id' => $trackId],
                    array_merge([
                        'invoice_id' => null,
                        'order_checkout_id' => $checkout->id,
                        'customer_id' => $checkout->customer_id,
                        'reference' => $checkout->order_number . '-' . substr($trackId, 0, 12),
                        'type' => Payment::TYPE_ORDER_CHECKOUT,
                        'payment_number' => $this->generatePaymentNumber(),
                        'payment_gateway_src' => $checkout->payment_gateway_src,
                        'amount' => $statusResult['amount'] ?? (float) $checkout->amount_due,
                        'bonus_amount' => 0,
                        'total_amount' => $statusResult['amount'] ?? (float) $checkout->amount_due,
                        'method' => 'online',
                        'payment_link' => $checkout->payment_link,
                        'status' => 'pending',
                    ], PaymentCreatorResolver::resolve($checkout->customer_id))
                );
            }
        } else {
            $checkout = OrderCheckout::query()->find($payment->order_checkout_id);
        }

        if (!$checkout) {
            return ['processed' => false, 'reason' => 'checkout_not_found'];
        }

        if ($payment && $payment->status === 'completed' && $checkout->order_id) {
            $order = Order::query()->with('invoice')->find($checkout->order_id);

            return [
                'processed' => true,
                'idempotent' => true,
                'order' => $order,
                'payment_status' => 'completed',
            ];
        }

        if (!($statusResult['is_success'] ?? false)) {
            if ($this->shouldAllowPaymentRetry()) {
                if ($payment && $payment->status !== Payment::STATUS_COMPLETED) {
                    $payment->update(['status' => Payment::STATUS_PENDING, 'paid_at' => null]);
                }

                return [
                    'processed' => true,
                    'payment_status' => Payment::STATUS_PENDING,
                ];
            }

            if ($payment && $payment->status !== Payment::STATUS_COMPLETED) {
                $payment->update(['status' => Payment::STATUS_FAILED]);
            }
            if ($checkout->isPending()) {
                $checkout->update(['status' => OrderCheckout::STATUS_FAILED]);
            }

            return [
                'processed' => true,
                'payment_status' => Payment::STATUS_FAILED,
            ];
        }

        return app(OrderCheckoutService::class)->fulfillCheckout($checkout, $payment, $statusResult);
    }

    /**
     * @return array{processed: bool, idempotent?: bool, order?: Order, payment_status?: string, reason?: string}
     */
    public function processVerifiedPayment(
        string $trackId,
        array $statusResult,
        ?string $receiptId = null,
        ?string $fallbackOrderNumber = null
    ): array {
        $existingPayment = Payment::query()
            ->where('gateway', 'ottu')
            ->where('track_id', $trackId)
            ->first();

        if ($existingPayment && $existingPayment->type === Payment::TYPE_ORDER_CHECKOUT) {
            return $this->processVerifiedCheckoutPayment($trackId, $statusResult);
        }

        $orderNumber = $statusResult['requested_order_id'] ?? $fallbackOrderNumber;
        $checkoutByNumber = $orderNumber
            ? OrderCheckout::query()->where('order_number', $orderNumber)->first()
            : null;

        if ($checkoutByNumber && !$checkoutByNumber->order_id) {
            return $this->processVerifiedCheckoutPayment($trackId, $statusResult);
        }

        $order = $orderNumber ? $this->orderRepository->findByOrderNumber($orderNumber) : null;
        if (!$order) {
            return ['processed' => false, 'reason' => 'order_not_found'];
        }
        $order->load('invoice');
        $invoice = $order->invoice;
        if (!$invoice) {
            return ['processed' => false, 'reason' => 'invoice_not_found'];
        }

        if (!($statusResult['is_success'] ?? false) && $this->shouldAllowPaymentRetry()) {
            $existingPayment = Payment::query()
                ->where('gateway', 'ottu')
                ->where('track_id', $trackId)
                ->first();

            if ($existingPayment && $existingPayment->status === Payment::STATUS_COMPLETED) {
                return [
                    'processed' => true,
                    'idempotent' => true,
                    'order' => $order,
                    'payment_status' => Payment::STATUS_COMPLETED,
                ];
            }

            if ($existingPayment && $existingPayment->status !== Payment::STATUS_COMPLETED) {
                $existingPayment->update(['status' => Payment::STATUS_PENDING, 'paid_at' => null]);
            }

            return [
                'processed' => true,
                'order' => $order,
                'payment_status' => Payment::STATUS_PENDING,
            ];
        }

        $gateway = 'ottu';
        $newStatus = $this->resolvePaymentStatus($statusResult);
        $amount = $statusResult['amount'] ?? (float) $invoice->amount_due;
        $orderFields = $this->orderPaymentFields($order, $invoice, $amount, $statusResult, $receiptId);

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
                $payment->update(array_merge($orderFields, [
                    'status' => $newStatus,
                    'paid_at' => $newStatus === 'completed' ? now('Asia/Kuwait') : null,
                    'tran_id' => $statusResult['tran_id'] ?? $payment->tran_id,
                    'payment_id' => $statusResult['payment_id'] ?? $payment->payment_id,
                    'receipt_id' => $statusResult['receipt_id'] ?? $payment->receipt_id ?? $receiptId,
                    'payment_link' => $payment->payment_link ?? $orderFields['payment_link'],
                ]));
            } else {
                $payment = Payment::create(array_merge($orderFields, [
                    'payment_number' => $this->generatePaymentNumber(),
                    'gateway' => $gateway,
                    'track_id' => $trackId,
                    'status' => $newStatus,
                    'paid_at' => $newStatus === 'completed' ? now('Asia/Kuwait') : null,
                ]));
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

            if ($newStatus === 'completed') {
                try {
                    $order->refresh();
                    $order->load('invoice');
                    DispatchErpOrderJob::dispatchAfterResponse($order->id);
                } catch (\Exception $e) {
                    Log::warning('Ottu payment recorded but post-payment dispatch failed', [
                        'track_id' => $trackId,
                        'order_id' => $order->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            try {
                if ($newStatus === 'completed' && $order->customer_id) {
                    sendNotification(
                        null,
                        $order->customer_id,
                        'Payment Successful',
                        "Payment for order {$order->order_number} was completed successfully.",
                        'payment',
                        [
                            'order_id' => $order->id,
                            'order_number' => $order->order_number,
                            'invoice_id' => $invoice->id,
                            'payment_id' => $payment->id,
                            'status' => $newStatus,
                        ],
                        'تم الدفع بنجاح',
                        "تمت عملية الدفع للطلب {$order->order_number} بنجاح."
                    );
                } elseif ($newStatus === 'failed' && $order->customer_id) {
                    sendNotification(
                        null,
                        $order->customer_id,
                        'Payment Failed',
                        "Payment for order {$order->order_number} failed. Please try again.",
                        'payment',
                        [
                            'order_id' => $order->id,
                            'order_number' => $order->order_number,
                            'invoice_id' => $invoice->id,
                            'payment_id' => $payment->id,
                            'status' => $newStatus,
                        ],
                        'فشل الدفع',
                        "فشلت عملية الدفع للطلب {$order->order_number}. يرجى المحاولة مرة أخرى."
                    );
                }
            } catch (\Exception $e) {
                Log::warning('Failed to dispatch verified payment notification', [
                    'track_id' => $trackId,
                    'order_number' => $orderNumber,
                    'message' => $e->getMessage(),
                ]);
            }

            return [
                'processed' => true,
                'order' => $order->fresh(),
                'payment_status' => $newStatus,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ottu processVerifiedPayment: exception', [
                'track_id' => $trackId,
                'requested_order_id' => $orderNumber,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function orderPaymentFields(
        Order $order,
        Invoice $invoice,
        float $amount,
        array $statusResult,
        ?string $receiptId
    ): array {
        return array_merge([
            'invoice_id' => $invoice->id,
            'customer_id' => $order->customer_id,
            'reference' => $order->order_number,
            'type' => Payment::TYPE_ORDER,
            'payment_gateway_src' => $order->payment_gateway_src,
            'amount' => $amount,
            'bonus_amount' => 0,
            'total_amount' => $amount,
            'method' => 'online',
            'payment_link' => $invoice->payment_link,
            'tran_id' => $statusResult['tran_id'] ?? null,
            'payment_id' => $statusResult['payment_id'] ?? null,
            'receipt_id' => $statusResult['receipt_id'] ?? $receiptId,
        ], PaymentCreatorResolver::fromOrder($order));
    }

    protected function generatePaymentNumber(): string
    {
        $year = date('Y');
        $pattern = 'PAY-' . $year . '-%';

        $lastPayment = Payment::where('payment_number', 'LIKE', $pattern)
            ->orderBy('payment_number', 'desc')
            ->first();

        $sequence = 1;
        if ($lastPayment) {
            $parts = explode('-', $lastPayment->payment_number);
            if (count($parts) === 3 && isset($parts[2])) {
                $sequence = (int) $parts[2] + 1;
            }
        }

        return sprintf('PAY-%s-%06d', $year, $sequence);
    }

    protected function shouldAllowPaymentRetry(): bool
    {
        return (bool) config('services.ottu.enable_pending_status', true);
    }

    public function resolvePaymentStatus(array $statusResult): string
    {
        if ($statusResult['is_success'] ?? false) {
            return Payment::STATUS_COMPLETED;
        }

        if ($this->shouldAllowPaymentRetry()) {
            return Payment::STATUS_PENDING;
        }

        return Payment::STATUS_FAILED;
    }
}
