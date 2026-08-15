<?php

namespace App\Services;

use App\Models\CustomerSubscription;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SubscriptionCheckout;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionOrderItem;
use App\Models\Subscription;
use App\Models\Customer;
use App\Support\PaymentCreatorResolver;
use App\Support\SubscriptionPricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SubscriptionPurchaseService
{
    public function __construct(
        protected PointsTransactionService $pointsTransactionService,
        protected InvoiceService $invoiceService,
        protected OttuService $ottuService,
        protected WalletService $walletService
    ) {
    }

    /**
     * Purchase a subscription with wallet (immediate) or start an online pay-first checkout.
     *
     * @return array{
     *     success: bool,
     *     checkout?: SubscriptionCheckout,
     *     customer_subscription?: CustomerSubscription,
     *     payment_link: string|null,
     *     is_checkout: bool
     * }
     */
    public function purchase(Customer $customer, array $data): array
    {
        $paymentGatewaySrc = $data['src'] ?? null;
        $isWalletPayment = ($data['payment_method'] ?? null) === 'wallet'
            || $paymentGatewaySrc === 'wallet';

        if ($isWalletPayment) {
            $paymentGatewaySrc = 'wallet';
        } elseif (!$paymentGatewaySrc) {
            throw new \Exception('Payment source (src) is required for online payment.', 400);
        }

        $subscription = Subscription::with([
            'offer.conditions.product',
            'offer.conditions.productVariant',
            'offer.rewards.product',
            'offer.rewards.productVariant'
        ])->findOrFail($data['subscription_id']);

        $startDate = Carbon::parse($data['start_date'] ?? now());
        $periodMonths = (int) $subscription->period;
        $endDate = $startDate->copy()->addMonths($periodMonths);
        $ordersPerMonth = (int) $data['orders_per_month'];
        $monthlyDeliveryTemplate = $data['delivery_schedule'];
        $deliverySchedule = $this->expandDeliveryScheduleFromMonthlyTemplate(
            $monthlyDeliveryTemplate,
            $periodMonths
        );

        $periodTotals = SubscriptionPricing::periodTotals($subscription->offer, $periodMonths);
        $subtotal = $periodTotals['total_after_price'];
        $invoiceAmounts = $this->invoiceService->calculateAmounts($subtotal, 0, 0, 0, 'delivery');
        $amountDue = $invoiceAmounts['amountDue'];
        $source = ($data['source'] ?? 'app') === 'web' ? 'web' : 'app';

        $checkoutAttributes = [
            'customer_id' => $customer->id,
            'checkout_number' => $this->generateCheckoutNumber(),
            'payload' => [
                'subscription_id' => $subscription->id,
                'offer_id' => $subscription->offer_id,
                'period' => $subscription->period,
                'points' => $subscription->points,
                'orders_per_month' => $ordersPerMonth,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'total_orders' => $periodMonths * $ordersPerMonth,
                'subtotal' => $subtotal,
                'total_before_price' => $periodTotals['total_before_price'],
                'total_after_price' => $periodTotals['total_after_price'],
                'invoice_amounts' => $invoiceAmounts,
                'monthly_delivery_template' => $monthlyDeliveryTemplate,
                'delivery_schedule' => $deliverySchedule,
                'source' => $source,
            ],
            'payment_gateway_src' => $paymentGatewaySrc,
            'source' => $source,
            'amount_due' => $amountDue,
            'status' => SubscriptionCheckout::STATUS_PENDING,
            'expires_at' => $isWalletPayment
                ? null
                : now()->addMinutes((int) config('services.ottu.checkout_ttl_minutes', 60)),
        ];

        if ($isWalletPayment) {
            return $this->purchaseWithWallet($customer, $checkoutAttributes, $amountDue);
        }

        return $this->purchaseOnline($checkoutAttributes, $amountDue, $paymentGatewaySrc);
    }

    /**
     * Pay immediately from wallet and create the subscription.
     *
     * @param  array<string, mixed>  $checkoutAttributes
     * @return array{success: bool, customer_subscription: CustomerSubscription, payment_link: null, is_checkout: false}
     */
    protected function purchaseWithWallet(Customer $customer, array $checkoutAttributes, float $amountDue): array
    {
        try {
            $this->walletService->validateBalance($customer->id, $amountDue);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), 400);
        }

        DB::beginTransaction();

        try {
            $checkout = SubscriptionCheckout::create($checkoutAttributes);

            $this->walletService->deductBalance($customer->id, $amountDue);

            $payment = Payment::create(array_merge([
                'invoice_id' => null,
                'subscription_checkout_id' => $checkout->id,
                'customer_id' => $customer->id,
                'reference' => $checkout->checkout_number,
                'type' => Payment::TYPE_SUBSCRIPTION,
                'payment_number' => $this->generatePaymentNumber(),
                'gateway' => 'wallet',
                'track_id' => $checkout->checkout_number,
                'payment_gateway_src' => 'wallet',
                'amount' => $amountDue,
                'bonus_amount' => 0,
                'total_amount' => $amountDue,
                'method' => 'wallet',
                'status' => Payment::STATUS_COMPLETED,
                'paid_at' => now('Asia/Kuwait'),
            ], PaymentCreatorResolver::forCustomer($customer->id)));

            $result = $this->fulfillPaidCheckout($checkout, $payment, []);
            if (empty($result['processed']) || empty($result['customer_subscription'])) {
                throw new \Exception('Failed to complete wallet subscription purchase.');
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();
            if (
                str_contains($message, 'Insufficient wallet balance')
                || str_contains($message, 'Customer wallet not found')
                || str_contains($message, 'Customer ID is required for wallet payment')
            ) {
                throw new \Exception($message, 400);
            }
            throw $e;
        }

        if (!empty($result['customer_subscription'])) {
            $this->notifySubscriptionPaymentSuccess(
                $result['checkout'] ?? $checkout,
                $result['customer_subscription'],
                $payment
            );
        }

        return [
            'success' => true,
            'customer_subscription' => $result['customer_subscription'],
            'payment_link' => null,
            'is_checkout' => false,
        ];
    }

    /**
     * Start a pay-first Ottu checkout. No subscription/orders/invoice until payment succeeds.
     *
     * @param  array<string, mixed>  $checkoutAttributes
     * @return array{success: bool, checkout: SubscriptionCheckout, payment_link: string, is_checkout: true}
     */
    protected function purchaseOnline(array $checkoutAttributes, float $amountDue, string $paymentGatewaySrc): array
    {
        $checkout = SubscriptionCheckout::create($checkoutAttributes);

        try {
            $checkout->load('customer');
            $paymentLink = $this->ottuService->createSubscriptionPayment(
                $checkout,
                $amountDue,
                $paymentGatewaySrc
            );
            $sessionId = $this->ottuService->getLastCheckoutSessionId();

            $checkout->update([
                'payment_link' => $paymentLink,
                'ottu_session_id' => $sessionId,
            ]);

            if ($sessionId) {
                $this->ottuService->ensurePendingSubscriptionPayment(
                    $checkout,
                    $sessionId,
                    $amountDue,
                    $paymentGatewaySrc,
                    $paymentLink
                );
            }
        } catch (\Throwable $e) {
            $checkout->update(['status' => SubscriptionCheckout::STATUS_FAILED]);
            Log::warning('Subscription checkout payment link generation failed', [
                'checkout_id' => $checkout->id,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return [
            'success' => true,
            'checkout' => $checkout->fresh(['customer']),
            'payment_link' => $paymentLink,
            'is_checkout' => true,
        ];
    }

    /**
     * Create the subscription, orders, and paid invoice after Ottu confirms payment.
     *
     * @return array{processed: bool, idempotent?: bool, customer_subscription?: CustomerSubscription, checkout?: SubscriptionCheckout, payment_status?: string, reason?: string}
     */
    public function fulfillCheckout(SubscriptionCheckout $checkout, Payment $payment, array $statusResult): array
    {
        DB::beginTransaction();

        try {
            $result = $this->fulfillPaidCheckout($checkout, $payment, $statusResult);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Subscription checkout fulfillment failed', [
                'checkout_id' => $checkout->id,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }

        if (
            !empty($result['processed'])
            && empty($result['idempotent'])
            && !empty($result['customer_subscription'])
        ) {
            $this->notifySubscriptionPaymentSuccess(
                $result['checkout'] ?? $checkout,
                $result['customer_subscription'],
                $payment
            );
        }

        return $result;
    }

    /**
     * Persist subscription, orders, invoice, and completed payment. Caller must be inside a transaction.
     *
     * @return array{processed: bool, idempotent?: bool, customer_subscription?: CustomerSubscription, checkout?: SubscriptionCheckout, payment_status?: string, reason?: string}
     */
    protected function fulfillPaidCheckout(SubscriptionCheckout $checkout, Payment $payment, array $statusResult): array
    {
        $locked = SubscriptionCheckout::query()->whereKey($checkout->id)->lockForUpdate()->first();
        if (!$locked) {
            return ['processed' => false, 'reason' => 'checkout_not_found'];
        }

        if ($locked->customer_subscription_id) {
            $customerSubscription = CustomerSubscription::query()
                ->with(['subscription.offer', 'orders', 'invoice'])
                ->find($locked->customer_subscription_id);

            return [
                'processed' => true,
                'idempotent' => true,
                'customer_subscription' => $customerSubscription,
                'checkout' => $locked,
                'payment_status' => Payment::STATUS_COMPLETED,
            ];
        }

        if (!$locked->isPending()) {
            if ($locked->status === SubscriptionCheckout::STATUS_FAILED) {
                $locked->update(['status' => SubscriptionCheckout::STATUS_PENDING]);
                $locked->refresh();
            } else {
                return ['processed' => false, 'reason' => 'checkout_not_pending', 'checkout' => $locked];
            }
        }

        $draft = $locked->draft();
        $subscription = Subscription::with([
            'offer.conditions.product',
            'offer.conditions.productVariant',
            'offer.rewards.product',
            'offer.rewards.productVariant',
        ])->findOrFail($draft['subscription_id']);

        $invoiceAmounts = $draft['invoice_amounts'] ?? [];
        $subtotal = (float) ($draft['subtotal'] ?? 0);

        $customerSubscription = CustomerSubscription::create([
            'customer_id' => $locked->customer_id,
            'subscription_id' => $subscription->id,
            'orders_per_month' => (int) ($draft['orders_per_month'] ?? 1),
            'start_date' => $draft['start_date'],
            'end_date' => $draft['end_date'],
            'status' => 'active',
            'total_amount' => $subtotal,
            'total_orders' => (int) ($draft['total_orders'] ?? 0),
            'source' => $locked->source ?: (($draft['source'] ?? 'app') === 'web' ? 'web' : 'app'),
            'metadata' => [
                'offer_id' => $draft['offer_id'] ?? $subscription->offer_id,
                'period' => $draft['period'] ?? $subscription->period,
                'points' => $draft['points'] ?? $subscription->points,
                'monthly_delivery_template' => $draft['monthly_delivery_template'] ?? [],
                'payment_gateway_src' => $locked->payment_gateway_src,
                'total_before_price' => (float) ($draft['total_before_price'] ?? $subtotal),
                'total_after_price' => (float) ($draft['total_after_price'] ?? $subtotal),
                'checkout_number' => $locked->checkout_number,
                'points_awarded' => false,
            ],
        ]);

        $this->generateSubscriptionOrders(
            $customerSubscription,
            $subscription,
            $draft['delivery_schedule'] ?? []
        );

        $invoice = $this->invoiceService->createOrGetSubscriptionInvoice(
            $customerSubscription->id,
            (float) ($invoiceAmounts['amountDue'] ?? $locked->amount_due),
            (float) ($invoiceAmounts['taxAmount'] ?? 0),
            (float) ($invoiceAmounts['deliveryFee'] ?? 0),
            (float) ($invoiceAmounts['totalDiscount'] ?? 0),
            true,
            'INV-' . $locked->checkout_number,
            $locked->payment_link
        );

        $referenceNumber = $statusResult['reference_number'] ?? null;
        $storedTrackId = (is_string($referenceNumber) && trim($referenceNumber) !== '')
            ? trim($referenceNumber)
            : (string) ($payment->track_id ?? '');

        $payment->update([
            'invoice_id' => $invoice->id,
            'subscription_checkout_id' => $locked->id,
            'type' => Payment::TYPE_SUBSCRIPTION,
            'reference' => $invoice->invoice_number,
            'status' => Payment::STATUS_COMPLETED,
            'paid_at' => now('Asia/Kuwait'),
            'track_id' => $storedTrackId,
            'tran_id' => $statusResult['tran_id'] ?? $payment->tran_id,
            'payment_id' => $statusResult['payment_id'] ?? $payment->payment_id,
            'receipt_id' => $statusResult['receipt_id'] ?? $payment->receipt_id,
        ]);

        $locked->update([
            'status' => SubscriptionCheckout::STATUS_PAID,
            'customer_subscription_id' => $customerSubscription->id,
        ]);

        $metadata = $customerSubscription->metadata ?? [];
        if ($subscription->points > 0 && empty($metadata['points_awarded'])) {
            $customer = Customer::query()->find($customerSubscription->customer_id);
            if ($customer) {
                $this->awardPoints($customer, $subscription->points);
                $metadata['points_awarded'] = true;
                $customerSubscription->update(['metadata' => $metadata]);
            }
        }

        foreach ($customerSubscription->orders()->get() as $order) {
            if (!$order->sent_to_erp_at) {
                $this->sendToErp($order);
            }
        }

        $customerSubscription->load([
            'subscription.offer.conditions.product',
            'subscription.offer.conditions.productVariant',
            'subscription.offer.rewards.product',
            'subscription.offer.rewards.productVariant',
            'orders' => fn ($q) => $q->orderBy('order_sequence'),
            'orders.items.product',
            'orders.items.productVariant',
            'invoice',
            'customer',
        ]);

        return [
            'processed' => true,
            'customer_subscription' => $customerSubscription,
            'checkout' => $locked->fresh(),
            'payment_status' => Payment::STATUS_COMPLETED,
        ];
    }

    protected function notifySubscriptionPaymentSuccess(
        SubscriptionCheckout $checkout,
        CustomerSubscription $customerSubscription,
        Payment $payment
    ): void {
        try {
            sendNotification(
                null,
                $customerSubscription->customer_id,
                'Subscription Payment Successful',
                "Payment for subscription {$checkout->checkout_number} was completed successfully.",
                'payment',
                [
                    'customer_subscription_id' => $customerSubscription->id,
                    'checkout_id' => $checkout->id,
                    'invoice_id' => $customerSubscription->invoice?->id,
                    'payment_id' => $payment->id,
                ],
                'تم دفع الاشتراك بنجاح',
                "تمت عملية الدفع للاشتراك {$checkout->checkout_number} بنجاح."
            );
        } catch (\Exception $e) {
            Log::warning('Failed to dispatch subscription payment notification', [
                'checkout_id' => $checkout->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function generateCheckoutNumber(): string
    {
        $prefix = 'SUB';
        $timestamp = now()->format('YmdHis');
        $random = strtoupper(substr(md5(uniqid((string) rand(), true)), 0, 4));

        return "{$prefix}-{$timestamp}-{$random}";
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

    /**
     * Activate a paid subscription: mark active, award points, send orders to ERP.
     */
    public function activateAfterPayment(CustomerSubscription $customerSubscription): void
    {
        DB::transaction(function () use ($customerSubscription) {
            $customerSubscription = CustomerSubscription::query()
                ->with(['subscription', 'invoice', 'customer', 'orders'])
                ->lockForUpdate()
                ->find($customerSubscription->id);

            if (!$customerSubscription) {
                return;
            }

            if ($customerSubscription->status !== 'pending_payment') {
                return;
            }

            $invoice = $customerSubscription->invoice;
            if ($invoice && $invoice->status !== 'paid') {
                $this->invoiceService->markAsPaid($invoice->id);
            }

            $customerSubscription->update(['status' => 'active']);

            $subscription = $customerSubscription->subscription;
            $metadata = $customerSubscription->metadata ?? [];

            if ($subscription && $subscription->points > 0 && empty($metadata['points_awarded'])) {
                $this->awardPoints($customerSubscription->customer, $subscription->points);
                $metadata['points_awarded'] = true;
                $customerSubscription->update(['metadata' => $metadata]);
            }

            foreach ($customerSubscription->orders as $order) {
                if (!$order->sent_to_erp_at) {
                    $this->sendToErp($order);
                }
            }
        });
    }

    /**
     * Activate the related subscription when its invoice is fully paid.
     */
    public function activateFromPaidInvoice(Invoice $invoice): void
    {
        if (!$invoice->customer_subscription_id) {
            return;
        }

        $customerSubscription = CustomerSubscription::query()->find($invoice->customer_subscription_id);
        if (!$customerSubscription) {
            return;
        }

        $this->activateAfterPayment($customerSubscription);
    }

    /**
     * Repeat one month of delivery dates for the full subscription period.
     * Example: ["2026-08-15", "2026-08-29"] × 3 months → Aug/Sep/Oct on the 15th and 29th.
     *
     * @param  array<int, string>  $monthlyTemplate
     * @return array<int, string>
     */
    public function expandDeliveryScheduleFromMonthlyTemplate(array $monthlyTemplate, int $periodMonths): array
    {
        if ($periodMonths < 1 || empty($monthlyTemplate)) {
            return [];
        }

        $schedule = [];

        for ($monthOffset = 0; $monthOffset < $periodMonths; $monthOffset++) {
            foreach ($monthlyTemplate as $date) {
                $schedule[] = Carbon::parse($date)->addMonths($monthOffset)->format('Y-m-d');
            }
        }

        return $schedule;
    }

    /**
     * Generate all subscription orders
     */
    protected function generateSubscriptionOrders(
        CustomerSubscription $customerSubscription,
        Subscription $subscription,
        array $deliverySchedule
    ): void {
        $ordersPerMonth = $customerSubscription->orders_per_month;
        $periodMonths = (int) $subscription->period;
        $offer = $subscription->offer;

        $orderSequence = 1;

        for ($month = 1; $month <= $periodMonths; $month++) {
            $monthItems = $this->distributeItemsForMonth($offer, $ordersPerMonth);

            for ($orderInMonth = 1; $orderInMonth <= $ordersPerMonth; $orderInMonth++) {
                $deliveryDate = $deliverySchedule[$orderSequence - 1] ?? null;

                if (!$deliveryDate) {
                    throw new \Exception("Delivery date missing for order {$orderSequence}");
                }

                $orderTotal = $this->calculateOrderTotal($monthItems[$orderInMonth - 1]);

                $subscriptionOrder = SubscriptionOrder::create([
                    'order_number' => SubscriptionOrder::generateOrderNumber(),
                    'customer_subscription_id' => $customerSubscription->id,
                    'customer_id' => $customerSubscription->customer_id,
                    'order_sequence' => $orderSequence,
                    'month_number' => $month,
                    'order_in_month' => $orderInMonth,
                    'scheduled_delivery_date' => $deliveryDate,
                    'status' => 'pending',
                    'total_amount' => $orderTotal,
                ]);

                $this->createOrderItems($subscriptionOrder, $monthItems[$orderInMonth - 1]);

                $orderSequence++;
            }
        }
    }

    /**
     * Distribute offer items across orders in a month
     * Example: 11 items, 2 orders -> [[6 items], [5 items]]
     */
    protected function distributeItemsForMonth($offer, $ordersPerMonth): array
    {
        $monthOrders = [];

        $conditionItems = [];
        foreach ($offer->conditions as $condition) {
            $conditionItems[] = [
                'type' => 'condition',
                'product_id' => $condition->product_id,
                'product_variant_id' => $condition->product_variant_id,
                'total_quantity' => $condition->quantity,
                'unit_price' => $condition->productVariant
                    ? $condition->productVariant->price
                    : $condition->product->price,
            ];
        }

        $rewardItems = [];
        if ($offer->reward_type === 'products') {
            foreach ($offer->rewards as $reward) {
                if ($reward->product_id) {
                    $rewardItems[] = [
                        'type' => 'reward',
                        'product_id' => $reward->product_id,
                        'product_variant_id' => $reward->product_variant_id,
                        'total_quantity' => $reward->quantity,
                        'unit_price' => $reward->productVariant
                            ? $reward->productVariant->price
                            : ($reward->product ? $reward->product->price : 0),
                    ];
                }
            }
        }

        $allItems = array_merge($conditionItems, $rewardItems);

        for ($i = 0; $i < $ordersPerMonth; $i++) {
            $orderItems = [];

            foreach ($allItems as $item) {
                $baseQty = floor($item['total_quantity'] / $ordersPerMonth);
                $remainder = $item['total_quantity'] % $ordersPerMonth;

                $quantity = $baseQty + ($i < $remainder ? 1 : 0);

                if ($quantity > 0) {
                    $orderItems[] = [
                        'type' => $item['type'],
                        'product_id' => $item['product_id'],
                        'product_variant_id' => $item['product_variant_id'],
                        'quantity' => $quantity,
                        'unit_price' => $item['unit_price'],
                    ];
                }
            }

            $monthOrders[] = $orderItems;
        }

        return $monthOrders;
    }

    /**
     * Create order items
     */
    protected function createOrderItems(SubscriptionOrder $order, array $items): void
    {
        foreach ($items as $item) {
            SubscriptionOrderItem::create([
                'subscription_order_id' => $order->id,
                'product_id' => $item['product_id'],
                'product_variant_id' => $item['product_variant_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total_price' => $item['quantity'] * $item['unit_price'],
                'type' => $item['type'],
            ]);
        }
    }

    /**
     * Calculate order total
     */
    protected function calculateOrderTotal(array $items): float
    {
        $total = 0;
        foreach ($items as $item) {
            $total += $item['quantity'] * $item['unit_price'];
        }
        return $total;
    }

    /**
     * Calculate payable subtotal for the subscription period (offer after-discount × months).
     */
    protected function calculateTotalAmount($offer, int $periodMonths): float
    {
        return SubscriptionPricing::periodTotals($offer, $periodMonths)['total_after_price'];
    }

    /**
     * Send order to ERP
     */
    protected function sendToErp(SubscriptionOrder $order): void
    {
        $order->update([
            'sent_to_erp_at' => now(),
            'erp_data' => [
                'status' => 'sent',
                'sent_at' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Award points to customer
     */
    protected function awardPoints(Customer $customer, int $points): void
    {
        if ($points <= 0) {
            return;
        }

        $customer->increment('points', $points);

        $this->pointsTransactionService->recordPointsEarned(
            $customer->id,
            $points,
            null,
            'Subscription purchase reward'
        );
    }
}
