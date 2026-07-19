<?php

namespace App\Services;

use App\Jobs\DispatchErpOrderJob;
use App\Jobs\SendOrderCreatedNotificationsJob;
use App\Jobs\SendPaymentLinkSmsJob;
use App\Models\Order;
use App\Models\OrderCheckout;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderCheckoutService
{
    public function __construct(
        protected OrderService $orderService,
        protected OttuService $ottuService,
    ) {}

    /**
     * @return array{success: bool, checkout: OrderCheckout, payment_link: ?string, is_checkout: true}
     */
    public function initiateCheckout(array $data): array
    {
        $draft = $this->orderService->prepareOrderDraft($data);
        $customerId = $data['customer_id'] ?? null;
        $paymentGatewaySrc = $data['src'] ?? null;

        if (!$paymentGatewaySrc) {
            throw new \Exception('Payment source (src) is required for online payment.');
        }

        $source = $data['source'] ?? 'call_center';
        $orderNumber = $this->orderService->generateOrderNumber($source);
        $amountDue = $draft->amountDue();
        $expiresAt = now()->addMinutes((int) config('services.ottu.checkout_ttl_minutes', 60));

        $checkout = OrderCheckout::create([
            'customer_id' => $customerId,
            'source' => $source,
            'order_number' => $orderNumber,
            'payload' => $draft->toPayloadArray(),
            'payment_gateway_src' => $paymentGatewaySrc,
            'amount_due' => $amountDue,
            'status' => OrderCheckout::STATUS_PENDING,
            'expires_at' => $expiresAt,
        ]);

        $paymentLink = null;

        try {
            $checkout->load('customer');
            $paymentLink = $this->ottuService->createCheckoutPayment($checkout, $amountDue, 25, $paymentGatewaySrc);
            $sessionId = $this->ottuService->getLastCheckoutSessionId();

            $checkout->update([
                'payment_link' => $paymentLink,
                'ottu_session_id' => $sessionId,
            ]);

            if ($sessionId) {
                $this->ottuService->ensurePendingCheckoutPayment(
                    $checkout,
                    $sessionId,
                    $amountDue,
                    $paymentGatewaySrc,
                    $paymentLink
                );
            }
        } catch (\Throwable $e) {
            $checkout->update(['status' => OrderCheckout::STATUS_FAILED]);
            Log::warning('Checkout payment link generation failed', [
                'checkout_id' => $checkout->id,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }

        $checkout->load(['customer']);

        if ($source === 'call_center' && $paymentLink) {
            SendPaymentLinkSmsJob::dispatch(checkoutId: $checkout->id)->afterResponse();
        }

        return [
            'success' => true,
            'checkout' => $checkout,
            'payment_link' => $paymentLink,
            'is_checkout' => true,
        ];
    }

    /**
     * @return array{success: bool, message: string, payment_link?: string}
     */
    public function regeneratePaymentLink(OrderCheckout $checkout, ?string $paymentGatewaySrc = null): array
    {
        if ($checkout->order_id) {
            return ['success' => false, 'message' => 'Checkout is already fulfilled.'];
        }

        if (!$checkout->isPending()) {
            return ['success' => false, 'message' => 'Checkout is no longer pending.'];
        }

        if ($checkout->expires_at && $checkout->expires_at->isPast()) {
            $checkout->update(['status' => OrderCheckout::STATUS_EXPIRED]);

            return ['success' => false, 'message' => 'Checkout has expired. Please create a new order.'];
        }

        $effectiveSrc = ($paymentGatewaySrc !== null && $paymentGatewaySrc !== '')
            ? $paymentGatewaySrc
            : $checkout->payment_gateway_src;

        if (!$effectiveSrc) {
            return ['success' => false, 'message' => 'No payment gateway source (src) is stored for this checkout.'];
        }

        $reusable = $this->ottuService->findReusablePaymentForCheckout($checkout, $effectiveSrc);
        if ($reusable) {
            Log::info('Reusing existing payment link for checkout ' . $checkout->id);

            return [
                'success' => true,
                'message' => 'Existing payment link is still valid. You can retry payment using the same link.',
                'payment_link' => $reusable['payment_link'],
                'reused' => true,
            ];
        }

        try {
            $checkout->load('customer');
            $paymentLink = $this->ottuService->createCheckoutPayment(
                $checkout,
                (float) $checkout->amount_due,
                null,
                $effectiveSrc
            );
            $sessionId = $this->ottuService->getLastCheckoutSessionId();

            $checkout->update([
                'payment_link' => $paymentLink,
                'ottu_session_id' => $sessionId,
                'payment_gateway_src' => $effectiveSrc,
                'expires_at' => now()->addMinutes((int) config('services.ottu.checkout_ttl_minutes', 60)),
            ]);

            if ($sessionId) {
                $this->ottuService->ensurePendingCheckoutPayment(
                    $checkout,
                    $sessionId,
                    (float) $checkout->amount_due,
                    $effectiveSrc,
                    $paymentLink
                );
            }

            if ($checkout->source === 'call_center' && $paymentLink) {
                SendPaymentLinkSmsJob::dispatch(checkoutId: $checkout->id)->afterResponse();
            }

            return [
                'success' => true,
                'message' => 'Payment link regenerated successfully.',
                'payment_link' => $paymentLink,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to regenerate checkout payment link', [
                'checkout_id' => $checkout->id,
                'message' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Failed to generate payment link: ' . $e->getMessage()];
        }
    }

    /**
     * @return array{success: bool, message: string, invoice_status?: string, payment_status?: string, order?: Order, gateway_status_raw?: mixed}
     */
    public function syncCheckoutPayment(OrderCheckout $checkout, ?string $sessionId = null): array
    {
        if ($checkout->order_id) {
            $order = Order::query()->with(['invoice', 'items.product', 'items.variant', 'customer'])->find($checkout->order_id);
            if ($order) {
                return [
                    'success' => true,
                    'message' => 'Checkout already fulfilled.',
                    'invoice_status' => $order->invoice?->status ?? 'paid',
                    'payment_status' => 'completed',
                    'order' => $order,
                ];
            }
        }

        if (!$checkout->isPending()) {
            return ['success' => false, 'message' => 'Checkout is no longer pending.'];
        }

        $sessionId = $sessionId ?: $checkout->ottu_session_id;
        if (!$sessionId) {
            return ['success' => false, 'message' => 'No Ottu session found for this checkout. Regenerate the payment link and try again.'];
        }

        try {
            $statusResult = $this->ottuService->getPaymentStatusWithRetries($sessionId);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Could not verify payment with Ottu: ' . $e->getMessage()];
        }

        if (!$statusResult['is_success']) {
            $paymentStatus = app(OttuPaymentProcessor::class)->resolvePaymentStatus($statusResult);

            return [
                'success' => false,
                'message' => ($statusResult['is_failed'] ?? false)
                    ? 'Payment was not completed. You can try again using the same or a new payment link.'
                    : 'Payment is not completed at Ottu yet.',
                'gateway_status_raw' => $statusResult['gateway_status_raw'] ?? null,
                'payment_status' => $paymentStatus,
            ];
        }

        try {
            $processResult = app(OttuPaymentProcessor::class)->processVerifiedCheckoutPayment(
                $sessionId,
                $statusResult
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

        $order = $processResult['order'] ?? null;

        return [
            'success' => true,
            'message' => 'Payment synced successfully.',
            'invoice_status' => $order?->invoice?->status ?? 'paid',
            'payment_status' => $processResult['payment_status'] ?? 'completed',
            'order' => $order,
            'gateway_status_raw' => $statusResult['gateway_status_raw'] ?? null,
        ];
    }

    /**
     * Fulfill checkout after verified online payment.
     *
     * @return array{processed: bool, idempotent?: bool, order?: Order, payment_status?: string, reason?: string}
     */
    public function fulfillCheckout(OrderCheckout $checkout, Payment $payment, array $statusResult): array
    {
        DB::beginTransaction();

        try {
            $locked = OrderCheckout::query()->whereKey($checkout->id)->lockForUpdate()->first();
            if (!$locked) {
                DB::rollBack();

                return ['processed' => false, 'reason' => 'checkout_not_found'];
            }

            if ($locked->order_id) {
                $order = Order::query()->with('invoice')->find($locked->order_id);
                DB::commit();

                return [
                    'processed' => true,
                    'idempotent' => true,
                    'order' => $order,
                    'payment_status' => 'completed',
                ];
            }

            if (!$locked->isPending()) {
                if ($locked->status === OrderCheckout::STATUS_FAILED) {
                    $locked->update(['status' => OrderCheckout::STATUS_PENDING]);
                    $locked->refresh();
                } else {
                    DB::rollBack();

                    return ['processed' => false, 'reason' => 'checkout_not_pending'];
                }
            }

            // Use the draft locked at checkout creation — do not re-validate stock or offer active status after payment.
            $storedDraft = OrderDraft::fromPayloadArray($locked->draft());

            $order = $this->orderService->createOrderFromDraft(
                $storedDraft,
                markInvoicePaid: true,
                reservedOrderNumber: $locked->order_number
            );

            $order->load('invoice');
            $invoice = $order->invoice;

            $payment->update([
                'invoice_id' => $invoice?->id,
                'order_checkout_id' => $locked->id,
                'type' => Payment::TYPE_ORDER,
                'reference' => $order->order_number,
                'status' => 'completed',
                'paid_at' => now('Asia/Kuwait'),
                'track_id' => $this->resolveCompletedTrackId($statusResult, $payment),
                'tran_id' => $statusResult['tran_id'] ?? $payment->tran_id,
                'payment_id' => $statusResult['payment_id'] ?? $payment->payment_id,
                'receipt_id' => $statusResult['receipt_id'] ?? $payment->receipt_id,
            ]);

            if ($invoice && $invoice->status !== 'paid') {
                $invoice->update([
                    'status' => 'paid',
                    'paid_at' => now('Asia/Kuwait'),
                    'payment_link' => $locked->payment_link,
                ]);
            }

            $locked->update([
                'status' => OrderCheckout::STATUS_PAID,
                'order_id' => $order->id,
            ]);

            DB::commit();

            DispatchErpOrderJob::dispatchAfterResponse($order->id);
            SendOrderCreatedNotificationsJob::dispatch($order->id)->afterResponse();

            $this->sendPaymentSuccessNotification($order, $payment);

            return [
                'processed' => true,
                'order' => $order->fresh(['invoice', 'customer', 'items.product', 'items.variant', 'customerAddress']),
                'payment_status' => 'completed',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Checkout fulfillment failed', [
                'checkout_id' => $checkout->id,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Prefer Ottu reference_number as track_id when payment completes.
     */
    protected function resolveCompletedTrackId(array $statusResult, Payment $payment): string
    {
        $referenceNumber = $statusResult['reference_number'] ?? null;
        if (is_string($referenceNumber) && trim($referenceNumber) !== '') {
            return trim($referenceNumber);
        }

        return (string) ($payment->track_id ?? '');
    }

    /**
     * @return array{success: bool, message: string, checkout?: OrderCheckout}
     */
    public function cancelCheckout(OrderCheckout $checkout, ?string $reason = null): array
    {
        if ($checkout->order_id) {
            return ['success' => false, 'message' => 'Checkout is already fulfilled and cannot be cancelled.'];
        }

        if (!$checkout->isPending()) {
            return ['success' => false, 'message' => 'Checkout is no longer pending.'];
        }

        $checkout->update(['status' => OrderCheckout::STATUS_CANCELLED]);

        Payment::query()
            ->where('order_checkout_id', $checkout->id)
            ->where('status', 'pending')
            ->update(['status' => 'failed']);

        return [
            'success' => true,
            'message' => 'Checkout cancelled successfully.',
            'checkout' => $checkout->fresh(),
        ];
    }

    public function expireStaleCheckouts(): int
    {
        $expired = OrderCheckout::query()
            ->where('status', OrderCheckout::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $checkout) {
            $checkout->update(['status' => OrderCheckout::STATUS_EXPIRED]);
            Payment::query()
                ->where('order_checkout_id', $checkout->id)
                ->where('status', 'pending')
                ->update(['status' => 'failed']);
        }

        return $expired->count();
    }

    protected function sendPaymentSuccessNotification(Order $order, Payment $payment): void
    {
        if (!$order->customer_id) {
            return;
        }

        try {
            sendNotification(
                null,
                $order->customer_id,
                'Payment Successful',
                "Payment for order {$order->order_number} was completed successfully.",
                'payment',
                [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'invoice_id' => $order->invoice?->id,
                    'payment_id' => $payment->id,
                    'status' => 'completed',
                ],
                'تم الدفع بنجاح',
                "تمت عملية الدفع للطلب {$order->order_number} بنجاح."
            );
        } catch (\Exception $e) {
            Log::warning('Failed to send checkout payment notification', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
