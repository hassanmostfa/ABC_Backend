<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderCheckout;
use App\Models\Payment;
use App\Support\PaymentCreatorResolver;

class OttuService
{
    protected ?string $lastCheckoutSessionId = null;

    protected const CHECKOUT_TXN_PATH = '/b/checkout/v1/pymt-txn';

    /** @var list<string> */
    protected const SIGNATURE_FIELD_NAMES = [
        'amount',
        'currency_code',
        'customer_address_city',
        'customer_address_country',
        'customer_address_line1',
        'customer_address_line2',
        'customer_address_postal_code',
        'customer_address_state',
        'customer_email',
        'customer_first_name',
        'customer_last_name',
        'customer_phone',
        'gateway_account',
        'gateway_name',
        'order_no',
        'reference_number',
        'result',
        'state',
    ];

    /** @var list<string> */
    protected const SUCCESS_STATUS_VALUES = [
        'success',
        'paid',
        'captured',
        'completed',
        'approved',
    ];

    /** @var list<string> */
    protected const FAILED_STATUS_VALUES = [
        'failed',
        'canceled',
        'cancelled',
        'error',
        'declined',
        'rejected',
    ];

    public function getLastCheckoutSessionId(): ?string
    {
        return $this->lastCheckoutSessionId;
    }
    protected function baseUrl(): string
    {
        $url = config('services.ottu.url');
        $url = is_string($url) ? trim($url) : '';
        if ($url === '') {
            throw new \RuntimeException(
                'Ottu base URL is empty. Set OTTU_TEST_URL / OTTU_LIVE_URL (or legacy OTTU_URL) and PAYMENTS_MODE in .env.'
            );
        }

        return rtrim($url, '/');
    }

    protected function apiKey(): string
    {
        $key = config('services.ottu.api_key');
        if (!is_string($key) || trim($key) === '') {
            throw new \RuntimeException(
                'Ottu API key is missing. Set OTTU_TEST_API_KEY / OTTU_LIVE_API_KEY (or legacy OTTU_API_KEY) and PAYMENTS_MODE in .env.'
            );
        }

        return trim($key);
    }

    protected function checkoutCreateEndpoint(): string
    {
        return $this->checkoutTxnEndpoint();
    }

    protected function checkoutStatusEndpoint(string $sessionId): string
    {
        return $this->checkoutTxnEndpoint(urlencode($sessionId) . '/');
    }

    /**
     * Build checkout API URL. Accepts either a domain base (sandbox/live)
     * or a full URL that already includes /b/checkout/v1/pymt-txn.
     */
    protected function checkoutTxnEndpoint(string $suffix = ''): string
    {
        $base = $this->baseUrl();

        if (preg_match('#(/b/checkout/v1/pymt-txn)/?$#', $base, $matches) === 1) {
            $root = preg_replace('#(/b/checkout/v1/pymt-txn)/?$#', $matches[1], $base);

            return rtrim($root, '/') . '/' . ltrim($suffix, '/');
        }

        return rtrim($base, '/') . self::CHECKOUT_TXN_PATH . '/' . ltrim($suffix, '/');
    }

    /**
     * Ottu requires a valid customer_email. Customers may have null/empty/invalid email in our DB.
     */
    protected function resolveCustomerEmail(object $customer): string
    {
        $email = trim((string) ($customer->email ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        $phone = preg_replace('/\D+/', '', (string) ($customer->phone ?? ''));
        if ($phone !== '') {
            return $phone . '@example.com';
        }

        return 'customer-' . ($customer->id ?? 'unknown') . '@example.com';
    }

    protected function resolveOttuErrorMessage(\Illuminate\Http\Client\Response $response): string
    {
        $body = $response->json();

        if (!is_array($body)) {
            $raw = trim((string) $response->body());

            return $raw !== ''
                ? mb_substr($raw, 0, 500)
                : 'Ottu request failed (HTTP ' . $response->status() . ')';
        }

        foreach (['message', 'detail', 'error'] as $key) {
            $value = $body[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        $encoded = json_encode($body);

        return ($encoded !== false && $encoded !== 'null' && $encoded !== '[]')
            ? $encoded
            : 'Ottu request failed (HTTP ' . $response->status() . ')';
    }

    /**
     * Create a payment transaction via Ottu Checkout API.
     *
     * @param string|null $pgCodeOverride  Request `src` / Ottu pg_code (e.g. "cc" → credit-card live / cyber-source-nbk test, "knet" → knet). Falls back to config.
     */
    public function createPayment(Order $order, float $amount, ?int $timeoutSeconds = null, ?string $pgCodeOverride = null): string
    {
        if (!$order->relationLoaded('customer')) {
            $order->load('customer');
        }
        if (!$order->relationLoaded('items')) {
            $order->load('items');
        }

        $customer = $order->customer;
        if (!$customer) {
            throw new \Exception('Customer is required for online payment');
        }

        $endpoint = $this->checkoutCreateEndpoint();

        $pgCode = $this->resolvePgCode($pgCodeOverride);

        $payload = [
            'type' => config('services.ottu.type', 'payment_request'),
            'pg_codes' => [$pgCode],
            'amount' => number_format($amount, 3, '.', ''),
            'currency_code' => config('services.ottu.currency', 'KWD'),
            'order_no' => $order->order_number,
            'customer_id' => (string) $customer->id,
            'customer_first_name' => $customer->name,
            'customer_email' => $this->resolveCustomerEmail($customer),
            'customer_phone' => $customer->phone,
            'webhook_url' => route('payments.notification'),
            'redirect_url' => route('payments.success'),
        ];

        $extra = $this->buildExtra($order);
        if (!empty($extra)) {
            $payload['extra'] = $extra;
        }

        $this->applyLiveCheckoutFields($payload, $customer, $order->order_number, $amount);

        Log::info('Ottu checkout request', [
            'endpoint' => $endpoint,
            'payload' => $payload,
        ]);

        $timeout = $timeoutSeconds ?? config('services.ottu.timeout', 60);
        $connectTimeout = min(10, (int) config('services.ottu.connect_timeout', 15));

        $response = Http::withHeaders([
            'Authorization' => 'Api-Key ' . $this->apiKey(),
            'Content-Type' => 'application/json',
        ])->connectTimeout($connectTimeout)->timeout($timeout)->post($endpoint, $payload);

        Log::info('Ottu checkout response', [
            'status' => $response->status(),
            'body' => $response->json(),
        ]);

        if (!$response->successful()) {
            $body = $response->json();
            $message = $this->resolveOttuErrorMessage($response);
            throw new \Exception($message);
        }

        $data = $response->json();

        $paymentUrl = $this->extractCheckoutLinkFromResponse($data, $pgCode);
        if ($paymentUrl === null) {
            Log::warning('Ottu checkout response had no checkout URL', ['data' => $data]);
            throw new \Exception('Payment URL not found in Ottu response');
        }

        $this->lastCheckoutSessionId = isset($data['session_id'])
            ? (string) $data['session_id']
            : $this->extractSessionIdFromUrl($paymentUrl);

        return $paymentUrl;
    }

    /**
     * Create a payment transaction for an order checkout (pay-first flow).
     *
     * @param string|null $pgCodeOverride Request `src` / Ottu pg_code.
     */
    public function createCheckoutPayment(
        OrderCheckout $checkout,
        float $amount,
        ?int $timeoutSeconds = null,
        ?string $pgCodeOverride = null
    ): string {
        $checkout->load('customer');
        $customer = $checkout->customer;
        if (!$customer) {
            throw new \Exception('Customer is required for online payment');
        }

        $endpoint = $this->checkoutCreateEndpoint();
        $pgCode = $this->resolvePgCode($pgCodeOverride);

        $payload = [
            'type' => config('services.ottu.type', 'payment_request'),
            'pg_codes' => [$pgCode],
            'amount' => number_format($amount, 3, '.', ''),
            'currency_code' => config('services.ottu.currency', 'KWD'),
            'order_no' => $checkout->order_number,
            'customer_id' => (string) $customer->id,
            'customer_first_name' => $customer->name,
            'customer_email' => $this->resolveCustomerEmail($customer),
            'customer_phone' => $customer->phone,
            'webhook_url' => route('payments.notification'),
            'redirect_url' => route('payments.success'),
            'extra' => [
                'payment_type' => Payment::TYPE_ORDER_CHECKOUT,
                'checkout_id' => (string) $checkout->id,
            ],
        ];

        $products = $this->buildCheckoutProducts($checkout);
        if (!empty($products)) {
            $payload['extra']['products'] = $products;
        }

        $this->applyLiveCheckoutFields(
            $payload,
            $customer,
            $checkout->order_number,
            $amount,
            Payment::TYPE_ORDER_CHECKOUT
        );

        Log::info('Ottu checkout payment request', [
            'endpoint' => $endpoint,
            'checkout_id' => $checkout->id,
            'order_no' => $checkout->order_number,
        ]);

        $timeout = $timeoutSeconds ?? config('services.ottu.timeout', 60);
        $connectTimeout = min(10, (int) config('services.ottu.connect_timeout', 15));

        $response = Http::withHeaders([
            'Authorization' => 'Api-Key ' . $this->apiKey(),
            'Content-Type' => 'application/json',
        ])->connectTimeout($connectTimeout)->timeout($timeout)->post($endpoint, $payload);

        if (!$response->successful()) {
            $body = $response->json();
            Log::warning('Ottu checkout response failed', [
                'status' => $response->status(),
                'body' => $body,
            ]);
            throw new \Exception($this->resolveOttuErrorMessage($response));
        }

        $data = $response->json();
        $paymentUrl = $this->extractCheckoutLinkFromResponse($data, $pgCode);
        if ($paymentUrl === null) {
            throw new \Exception('Payment URL not found in Ottu response');
        }

        $this->lastCheckoutSessionId = isset($data['session_id'])
            ? (string) $data['session_id']
            : $this->extractSessionIdFromUrl($paymentUrl);

        return $paymentUrl;
    }

    /**
     * Store a pending payment row for a checkout session.
     */
    public function ensurePendingCheckoutPayment(
        OrderCheckout $checkout,
        string $sessionId,
        float $amount,
        ?string $paymentGatewaySrc = null,
        ?string $paymentLink = null
    ): Payment {
        $reference = $checkout->order_number . '-' . substr($sessionId, 0, 12);

        $attributes = [
            'invoice_id' => null,
            'order_checkout_id' => $checkout->id,
            'customer_id' => $checkout->customer_id,
            'reference' => $reference,
            'type' => Payment::TYPE_ORDER_CHECKOUT,
            'payment_gateway_src' => $paymentGatewaySrc ?? $checkout->payment_gateway_src,
            'amount' => $amount,
            'bonus_amount' => 0,
            'total_amount' => $amount,
            'method' => 'online',
            'payment_link' => $paymentLink ?? $checkout->payment_link,
        ];

        $payment = Payment::firstOrCreate(
            ['gateway' => 'ottu', 'track_id' => $sessionId],
            array_merge($attributes, PaymentCreatorResolver::resolve($checkout->customer_id), [
                'payment_number' => $this->generatePaymentNumber(),
                'status' => Payment::STATUS_PENDING,
            ])
        );

        if (!$payment->wasRecentlyCreated) {
            $payment->fill($attributes);
            if ($payment->status !== Payment::STATUS_COMPLETED) {
                $payment->status = Payment::STATUS_PENDING;
                $payment->paid_at = null;
            }
            $payment->save();
        }

        return $payment->fresh();
    }

    /**
     * @return list<array{name: string, price: float, quantity: int}>
     */
    protected function buildCheckoutProducts(OrderCheckout $checkout): array
    {
        $draft = OrderDraft::fromPayloadArray($checkout->draft());
        $products = [];

        foreach ($draft->orderItemsData as $item) {
            $products[] = [
                'name' => (string) ($item['name'] ?? 'Item'),
                'price' => (float) ($item['unit_price'] ?? 0),
                'quantity' => (int) ($item['quantity'] ?? 1),
            ];
        }

        return $products;
    }

    /**
     * Create a payment transaction for wallet top-up.
     *
     * @param string|null $pgCodeOverride  Request `src` / Ottu pg_code. Falls back to config.
     */
    public function createWalletChargePayment(Payment $payment, float $amount, ?string $pgCodeOverride = null): string
    {
        $payment->load('customer');
        $customer = $payment->customer;
        if (!$customer) {
            throw new \Exception('Customer is required for wallet charge payment');
        }

        $endpoint = $this->checkoutCreateEndpoint();

        $pgCode = $this->resolvePgCode($pgCodeOverride);

        $payload = [
            'type' => config('services.ottu.type', 'payment_request'),
            'pg_codes' => [$pgCode],
            'amount' => number_format($amount, 3, '.', ''),
            'currency_code' => config('services.ottu.currency', 'KWD'),
            'order_no' => $payment->reference,
            'customer_id' => (string) $customer->id,
            'customer_first_name' => $customer->name,
            'customer_email' => $this->resolveCustomerEmail($customer),
            'customer_phone' => $customer->phone,
            'webhook_url' => route('payments.wallet-charge.notification'),
            'redirect_url' => route('payments.wallet-charge.success'),
            'extra' => [
                'payment_type' => 'wallet_charge',
                'payment_id' => (string) $payment->id,
            ],
        ];

        $this->applyLiveCheckoutFields(
            $payload,
            $customer,
            (string) $payment->reference,
            $amount,
            'wallet_charge'
        );

        Log::info('Ottu wallet charge request', [
            'endpoint' => $endpoint,
            'wallet_charge_reference' => $payment->reference,
        ]);

        $timeout = config('services.ottu.timeout', 60);
        $connectTimeout = config('services.ottu.connect_timeout', 15);

        $response = Http::withHeaders([
            'Authorization' => 'Api-Key ' . $this->apiKey(),
            'Content-Type' => 'application/json',
        ])->connectTimeout($connectTimeout)->timeout($timeout)->post($endpoint, $payload);

        Log::info('Ottu wallet charge response', [
            'status' => $response->status(),
            'body' => $response->json(),
        ]);

        if (!$response->successful()) {
            $body = $response->json();
            $message = $this->resolveOttuErrorMessage($response);
            throw new \Exception($message);
        }

        $data = $response->json();

        $paymentUrl = $this->extractCheckoutLinkFromResponse($data, $pgCode);
        if ($paymentUrl === null) {
            Log::warning('Ottu wallet charge response had no checkout URL', ['data' => $data]);
            throw new \Exception('Payment URL not found in Ottu response');
        }

        $this->lastCheckoutSessionId = isset($data['session_id'])
            ? (string) $data['session_id']
            : $this->extractSessionIdFromUrl($paymentUrl);

        return $paymentUrl;
    }

    /**
     * Store a pending payment row when checkout is created so callbacks can resolve the order by session_id.
     */
    public function ensurePendingOrderPayment(
        Invoice $invoice,
        Order $order,
        string $sessionId,
        float $amount,
        ?string $paymentGatewaySrc = null,
        ?string $paymentLink = null
    ): Payment {
        $link = $paymentLink ?? $invoice->payment_link;

        $attributes = [
            'invoice_id' => $invoice->id,
            'customer_id' => $order->customer_id,
            'reference' => $order->order_number,
            'type' => Payment::TYPE_ORDER,
            'payment_gateway_src' => $paymentGatewaySrc ?? $order->payment_gateway_src,
            'amount' => $amount,
            'bonus_amount' => 0,
            'total_amount' => $amount,
            'method' => 'online',
            'payment_link' => $link,
        ];

        $payment = Payment::firstOrCreate(
            ['gateway' => 'ottu', 'track_id' => $sessionId],
            array_merge($attributes, PaymentCreatorResolver::fromOrder($order), [
                'payment_number' => $this->generatePaymentNumber(),
                'status' => Payment::STATUS_PENDING,
            ])
        );

        if (!$payment->wasRecentlyCreated) {
            $payment->fill($attributes);
            if ($payment->status !== Payment::STATUS_COMPLETED) {
                $payment->status = Payment::STATUS_PENDING;
                $payment->paid_at = null;
            }
            $payment->save();
        }

        return $payment->fresh();
    }

    /**
     * Find an existing Ottu payment link that can be reused for retry (invoice not yet paid).
     *
     * @return array{payment_link: string, session_id: string, payment: Payment|null}|null
     */
    public function findReusablePaymentForInvoice(Invoice $invoice, ?string $requiredSrc = null): ?array
    {
        if ($invoice->status === 'paid') {
            return null;
        }

        $payments = Payment::query()
            ->where('invoice_id', $invoice->id)
            ->where('gateway', 'ottu')
            ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_FAILED])
            ->whereNotNull('track_id')
            ->orderByDesc('id')
            ->get();

        foreach ($payments as $payment) {
            if ($requiredSrc !== null && $requiredSrc !== ''
                && $payment->payment_gateway_src
                && $payment->payment_gateway_src !== $requiredSrc) {
                continue;
            }

            $link = is_string($payment->payment_link) && $payment->payment_link !== ''
                ? $payment->payment_link
                : (is_string($invoice->payment_link) ? $invoice->payment_link : null);

            if (!$link) {
                continue;
            }

            if ($payment->status === Payment::STATUS_FAILED) {
                $payment->update(['status' => Payment::STATUS_PENDING, 'paid_at' => null]);
            }

            return [
                'payment_link' => $link,
                'session_id' => (string) $payment->track_id,
                'payment' => $payment->fresh(),
            ];
        }

        $invoiceLink = $invoice->payment_link;
        if (!is_string($invoiceLink) || $invoiceLink === '') {
            return null;
        }

        $sessionId = $this->extractSessionIdFromUrl($invoiceLink);
        if (!$sessionId) {
            return null;
        }

        return [
            'payment_link' => $invoiceLink,
            'session_id' => $sessionId,
            'payment' => null,
        ];
    }

    /**
     * Find an existing Ottu payment link that can be reused for a pending checkout.
     *
     * @return array{payment_link: string, session_id: string, payment: Payment|null}|null
     */
    public function findReusablePaymentForCheckout(OrderCheckout $checkout, ?string $requiredSrc = null): ?array
    {
        if (!$checkout->isPending()) {
            return null;
        }

        if ($checkout->expires_at && $checkout->expires_at->isPast()) {
            return null;
        }

        $effectiveSrc = $requiredSrc ?: $checkout->payment_gateway_src;

        $payments = Payment::query()
            ->where('order_checkout_id', $checkout->id)
            ->where('gateway', 'ottu')
            ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_FAILED])
            ->whereNotNull('track_id')
            ->orderByDesc('id')
            ->get();

        foreach ($payments as $payment) {
            if ($effectiveSrc !== null && $effectiveSrc !== ''
                && $payment->payment_gateway_src
                && $payment->payment_gateway_src !== $effectiveSrc) {
                continue;
            }

            $link = is_string($payment->payment_link) && $payment->payment_link !== ''
                ? $payment->payment_link
                : (is_string($checkout->payment_link) ? $checkout->payment_link : null);

            if (!$link) {
                continue;
            }

            if ($payment->status === Payment::STATUS_FAILED) {
                $payment->update(['status' => Payment::STATUS_PENDING, 'paid_at' => null]);
            }

            return [
                'payment_link' => $link,
                'session_id' => (string) $payment->track_id,
                'payment' => $payment->fresh(),
            ];
        }

        $checkoutLink = $checkout->payment_link;
        $sessionId = $checkout->ottu_session_id ?: (
            is_string($checkoutLink) ? $this->extractSessionIdFromUrl($checkoutLink) : null
        );

        if (!is_string($checkoutLink) || $checkoutLink === '' || !$sessionId) {
            return null;
        }

        return [
            'payment_link' => $checkoutLink,
            'session_id' => $sessionId,
            'payment' => null,
        ];
    }

    public function extractSessionIdFromUrl(string $paymentUrl): ?string
    {
        $query = parse_url($paymentUrl, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return null;
        }

        parse_str($query, $params);

        $sessionId = $params['session_id'] ?? null;

        return is_string($sessionId) && $sessionId !== '' ? $sessionId : null;
    }

    /**
     * Retrieve payment transaction status from Ottu.
     *
     * @return array{gateway_status_raw: mixed, is_success: bool, is_failed: bool, amount: float|null, currency: string|null, track_id: string, receipt_id: string|null, payment_id: string|null, tran_id: string|null, requested_order_id: string|null}
     */
    public function getPaymentStatus(string $sessionId): array
    {
        $url = $this->checkoutStatusEndpoint($sessionId);

        $timeout = 20;
        $maxAttempts = 3;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Api-Key ' . $this->apiKey(),
                    'Accept' => 'application/json',
                ])->timeout($timeout)->get($url);

                $body = is_array($response->json()) ? $response->json() : [];

                if (!$response->successful()) {
                    Log::warning('Ottu getPaymentStatus non-success HTTP response', [
                        'session_id' => $sessionId,
                        'status' => $response->status(),
                        'body' => $body,
                    ]);
                }

                return $this->buildStatusResultFromCheckoutBody($body, $sessionId);
            } catch (\Exception $e) {
                $lastException = $e;
                Log::warning('Ottu getPaymentStatus attempt failed', [
                    'session_id' => $sessionId,
                    'attempt' => $attempt,
                    'message' => $e->getMessage(),
                ]);
                if ($attempt < $maxAttempts) {
                    usleep(500000);
                }
            }
        }

        throw $lastException ?? new \Exception('Ottu getPaymentStatus failed');
    }

    /**
     * Poll Ottu until the transaction reaches a terminal state or attempts are exhausted.
     *
     * @return array{gateway_status_raw: mixed, is_success: bool, is_failed: bool, amount: float|null, currency: string|null, track_id: string, receipt_id: string|null, payment_id: string|null, tran_id: string|null, requested_order_id: string|null}
     */
    public function getPaymentStatusWithRetries(string $sessionId, int $maxAttempts = 6, int $delaySeconds = 1): array
    {
        $last = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $last = $this->getPaymentStatus($sessionId);
            if ($last['is_success'] || $last['is_failed']) {
                return $last;
            }

            if ($attempt < $maxAttempts) {
                sleep(max(1, $delaySeconds));
            }
        }

        return $last ?? $this->getPaymentStatus($sessionId);
    }

    /**
     * @return array{gateway_status_raw: mixed, is_success: bool, is_failed: bool, amount: float|null, currency: string|null, track_id: string, receipt_id: string|null, payment_id: string|null, tran_id: string|null, requested_order_id: string|null}
     */
    public function buildStatusResultFromWebhook(array $payload, string $sessionId): array
    {
        return $this->buildStatusResultFromCheckoutBody($payload, $sessionId);
    }

    /**
     * @return array{gateway_status_raw: mixed, is_success: bool, is_failed: bool, amount: float|null, currency: string|null, track_id: string, receipt_id: string|null, payment_id: string|null, tran_id: string|null, requested_order_id: string|null}
     */
    protected function buildStatusResultFromCheckoutBody(array $body, string $sessionId): array
    {
        $result = $body['result'] ?? null;
        $state = $body['state'] ?? null;
        $pgParams = is_array($body['pg_params'] ?? null) ? $body['pg_params'] : [];

        return [
            'gateway_status_raw' => $result ?? $state,
            'is_success' => $this->isSuccessfulPayment($result, $state, $body),
            'is_failed' => $this->isFailedPayment($result, $state),
            'amount' => isset($body['amount']) ? (float) $body['amount'] : null,
            'currency' => isset($body['currency_code']) ? (string) $body['currency_code'] : null,
            'track_id' => $sessionId,
            'receipt_id' => $pgParams['receipt_no'] ?? $pgParams['receipt_id'] ?? null,
            'payment_id' => $pgParams['payment_id']
                ?? (isset($body['reference_number']) ? (string) $body['reference_number'] : null),
            'tran_id' => $pgParams['transaction_id']
                ?? $pgParams['tran_id']
                ?? $pgParams['ref']
                ?? $pgParams['track_id']
                ?? null,
            'requested_order_id' => $body['order_no'] ?? null,
        ];
    }

    public function isSuccessfulPayment(mixed $result, mixed $state, ?array $body = null): bool
    {
        $resultLower = is_string($result) ? strtolower(trim($result)) : '';
        $stateLower = is_string($state) ? strtolower(trim($state)) : '';

        if (in_array($resultLower, self::SUCCESS_STATUS_VALUES, true)
            || in_array($stateLower, self::SUCCESS_STATUS_VALUES, true)) {
            return true;
        }

        if ($body !== null) {
            $pgParams = is_array($body['pg_params'] ?? null) ? $body['pg_params'] : [];
            $pgResult = isset($pgParams['result']) ? strtolower(trim((string) $pgParams['result'])) : '';
            if (in_array($pgResult, ['captured', 'approved', 'success', 'paid'], true)) {
                return true;
            }
        }

        return false;
    }

    public function isFailedPayment(mixed $result, mixed $state): bool
    {
        $resultLower = is_string($result) ? strtolower(trim($result)) : '';
        $stateLower = is_string($state) ? strtolower(trim($state)) : '';

        return in_array($resultLower, self::FAILED_STATUS_VALUES, true)
            || in_array($stateLower, self::FAILED_STATUS_VALUES, true);
    }

    /**
     * Verify browser redirect query params when Ottu includes a signature.
     * If no signature is sent, returns true (caller must still verify via getPaymentStatus API).
     */
    public function verifyRedirectParams(array $params): bool
    {
        if (!isset($params['signature']) || $params['signature'] === '') {
            return true;
        }

        return $this->verifySignature($params);
    }

    /**
     * Verify webhook signature using HMAC-SHA256.
     * Returns true if the computed signature matches the one in the payload.
     */
    public function verifySignature(array $payload): bool
    {
        if (config('services.ottu.skip_signature_verify', false)) {
            Log::warning('Ottu webhook signature verification skipped (OTTU_SKIP_SIGNATURE_VERIFY)');

            return true;
        }

        $hmacKey = config('services.ottu.hmac_key');
        if (empty($hmacKey)) {
            Log::warning('Ottu HMAC key not configured, skipping signature verification');

            return true;
        }

        $receivedSignature = $payload['signature'] ?? null;
        if (empty($receivedSignature)) {
            return false;
        }

        $message = '';
        foreach (self::SIGNATURE_FIELD_NAMES as $key) {
            if (isset($payload[$key]) && $payload[$key] !== '') {
                $message .= $key . $payload[$key];
            }
        }

        $computed = hash_hmac('sha256', $message, (string) $hmacKey);

        return hash_equals($computed, (string) $receivedSignature);
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
     * Resolve customer-facing URL from Checkout API create response.
     * When $resolvedPgCode is set, prefer that method's redirect_url (includes pg_code) so the payer skips method selection.
     */
    protected function extractCheckoutLinkFromResponse(array $data, ?string $resolvedPgCode = null): ?string
    {
        $pg = $resolvedPgCode !== null && trim($resolvedPgCode) !== ''
            ? strtolower(trim($resolvedPgCode))
            : null;

        if ($pg !== null) {
            foreach ($data['payment_methods'] ?? [] as $method) {
                if (!is_array($method)) {
                    continue;
                }
                $methodCode = isset($method['code']) ? strtolower((string) $method['code']) : '';
                if ($methodCode === $pg && !empty($method['redirect_url'])) {
                    $url = trim((string) $method['redirect_url']);
                    if ($url !== '' && (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'))) {
                        return $url;
                    }
                }
            }

            $checkoutUrl = isset($data['checkout_url']) ? trim((string) $data['checkout_url']) : '';
            if ($checkoutUrl !== '' && (str_starts_with($checkoutUrl, 'http://') || str_starts_with($checkoutUrl, 'https://'))) {
                if (!str_contains($checkoutUrl, 'pg_code=')) {
                    $sep = str_contains($checkoutUrl, '?') ? '&' : '?';

                    return $checkoutUrl . $sep . 'pg_code=' . rawurlencode($pg);
                }

                return $checkoutUrl;
            }
        }

        $candidates = [
            $data['checkout_url'] ?? null,
            $data['payment_url'] ?? null,
            $data['checkout_page_url'] ?? null,
        ];

        foreach ($data['payment_methods'] ?? [] as $method) {
            if (is_array($method) && isset($method['redirect_url'])) {
                $candidates[] = $method['redirect_url'];
            }
        }

        foreach ($candidates as $value) {
            if (is_string($value)) {
                $value = trim($value);
                if ($value !== '' && (str_starts_with($value, 'http://') || str_starts_with($value, 'https://'))) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Map legacy Upayments-style `src` (cc, knet) to Ottu checkout pg_codes slug.
     */
    protected function normalizeSrcToPgCode(string $raw): string
    {
        $s = strtolower(trim($raw));

        return match ($s) {
            'cc',
            'card',
            'credit',
            'creditcard',
            'credit_card',
            'credit-card' => $this->creditCardPgCode(),
            'knet' => 'knet',
            default => $s,
        };
    }

    protected function creditCardPgCode(): string
    {
        return config('payments.mode') === 'live' ? 'credit-card' : 'cyber-source-nbk';
    }

    protected function isLiveMode(): bool
    {
        return config('payments.mode') === 'live';
    }

    /**
     * Ottu live checkout requires customer_last_name and extra.payment_description.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function applyLiveCheckoutFields(
        array &$payload,
        object $customer,
        string $reference,
        float $amount,
        ?string $paymentType = null
    ): void {
        if (!$this->isLiveMode()) {
            return;
        }

        $name = $this->splitCustomerName($customer->name ?? null);
        $payload['customer_first_name'] = $name['first'];
        $payload['customer_last_name'] = $name['last'];

        $payload['extra'] = array_merge(
            is_array($payload['extra'] ?? null) ? $payload['extra'] : [],
            [
                'payment_description' => $this->paymentDescription($reference, $amount, $paymentType),
            ]
        );
    }

    /**
     * @return array{first: string, last: string}
     */
    protected function splitCustomerName(?string $fullName): array
    {
        $fullName = trim((string) $fullName);
        if ($fullName === '') {
            return ['first' => 'Customer', 'last' => 'Customer'];
        }

        $parts = preg_split('/\s+/', $fullName, 2);
        $first = $parts[0] ?? 'Customer';
        $last = $parts[1] ?? $first;

        return ['first' => $first, 'last' => $last];
    }

    protected function paymentDescription(string $reference, float $amount, ?string $paymentType = null): string
    {
        $currency = config('services.ottu.currency', 'KWD');
        $formattedAmount = number_format($amount, 3, '.', '');

        return match ($paymentType) {
            'wallet_charge' => "Wallet top-up {$reference} ({$formattedAmount} {$currency})",
            Payment::TYPE_ORDER_CHECKOUT => "Order payment {$reference} ({$formattedAmount} {$currency})",
            default => "Payment for {$reference} ({$formattedAmount} {$currency})",
        };
    }

    /**
     * Resolve the pg_code to use. Prefers override (and normalizes src aliases), then config, then default.
     */
    protected function resolvePgCode(?string $override): string
    {
        $raw = $override !== null && trim($override) !== ''
            ? trim($override)
            : trim((string) config('services.ottu.pg_code', ''));

        if ($raw === '') {
            return $this->creditCardPgCode();
        }

        return $this->normalizeSrcToPgCode($raw);
    }

    /**
     * Build extra metadata for the checkout payload.
     */
    protected function buildExtra(Order $order): array
    {
        $extra = [
            'order_id' => (string) $order->id,
        ];

        if ($order->relationLoaded('items') && $order->items->isNotEmpty()) {
            $products = $order->items->map(fn ($item) => [
                'name' => $item->name,
                'price' => (float) $item->unit_price,
                'quantity' => (int) $item->quantity,
            ])->toArray();
            $extra['products'] = $products;
        }

        return $extra;
    }
}
