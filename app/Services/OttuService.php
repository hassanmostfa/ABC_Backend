<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Models\Payment;

class OttuService
{
    protected function baseUrl(): string
    {
        $url = config('services.ottu.url');
        $url = is_string($url) ? trim($url) : '';
        if ($url === '') {
            throw new \RuntimeException(
                'Ottu base URL is empty. Set OTTU_URL in .env (e.g. https://sandbox.ottu.net for sandbox).'
            );
        }

        return rtrim($url, '/');
    }

    protected function apiKey(): string
    {
        $key = config('services.ottu.api_key');
        if (!is_string($key) || trim($key) === '') {
            throw new \RuntimeException(
                'Ottu API key is missing. Set OTTU_API_KEY in your .env file (Ottu dashboard → API keys).'
            );
        }

        return trim($key);
    }

    /**
     * Create a payment transaction via Ottu Checkout API.
     *
     * @param string|null $pgCodeOverride  Request `src` / Ottu pg_code (e.g. "cc" → credit-card, "knet"). Falls back to config.
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

        $endpoint = $this->baseUrl() . '/b/checkout/v1/pymt-txn/';

        $pgCode = $this->resolvePgCode($pgCodeOverride);

        $payload = [
            'type' => config('services.ottu.type', 'payment_request'),
            'pg_codes' => [$pgCode],
            'amount' => number_format($amount, 3, '.', ''),
            'currency_code' => config('services.ottu.currency', 'KWD'),
            'order_no' => $order->order_number,
            'customer_id' => (string) $customer->id,
            'customer_first_name' => $customer->name,
            'customer_email' => $customer->email ?? $customer->phone . '@example.com',
            'customer_phone' => $customer->phone,
            'webhook_url' => route('payments.notification'),
            'redirect_url' => route('payments.success'),
        ];

        if (str_starts_with((string) $order->order_number, 'WEB-')) {
            $payload['redirect_url'] = config('services.ottu.website_return_url');
        }

        $extra = $this->buildExtra($order);
        if (!empty($extra)) {
            $payload['extra'] = $extra;
        }

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
            $message = $body['message'] ?? $body['detail'] ?? json_encode($body) ?: 'Ottu request failed';
            throw new \Exception($message);
        }

        $data = $response->json();

        $paymentUrl = $this->extractCheckoutLinkFromResponse($data, $pgCode);
        if ($paymentUrl === null) {
            Log::warning('Ottu checkout response had no checkout URL', ['data' => $data]);
            throw new \Exception('Payment URL not found in Ottu response');
        }

        return $paymentUrl;
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

        $endpoint = $this->baseUrl() . '/b/checkout/v1/pymt-txn/';

        $pgCode = $this->resolvePgCode($pgCodeOverride);

        $payload = [
            'type' => config('services.ottu.type', 'payment_request'),
            'pg_codes' => [$pgCode],
            'amount' => number_format($amount, 3, '.', ''),
            'currency_code' => config('services.ottu.currency', 'KWD'),
            'order_no' => $payment->reference,
            'customer_id' => (string) $customer->id,
            'customer_first_name' => $customer->name,
            'customer_email' => $customer->email ?? $customer->phone . '@example.com',
            'customer_phone' => $customer->phone,
            'webhook_url' => route('payments.wallet-charge.notification'),
            'redirect_url' => route('payments.wallet-charge.success'),
            'extra' => [
                'payment_type' => 'wallet_charge',
                'payment_id' => (string) $payment->id,
            ],
        ];

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
            $message = $body['message'] ?? $body['detail'] ?? json_encode($body) ?: 'Ottu request failed';
            throw new \Exception($message);
        }

        $data = $response->json();

        $paymentUrl = $this->extractCheckoutLinkFromResponse($data, $pgCode);
        if ($paymentUrl === null) {
            Log::warning('Ottu wallet charge response had no checkout URL', ['data' => $data]);
            throw new \Exception('Payment URL not found in Ottu response');
        }

        return $paymentUrl;
    }

    /**
     * Retrieve payment transaction status from Ottu.
     *
     * @return array{gateway_status_raw: mixed, is_success: bool, is_failed: bool, amount: float|null, currency: string|null, track_id: string, receipt_id: string|null, payment_id: string|null, tran_id: string|null, requested_order_id: string|null}
     */
    public function getPaymentStatus(string $sessionId): array
    {
        $url = $this->baseUrl() . '/b/checkout/v1/pymt-txn/' . urlencode($sessionId) . '/';

        $timeout = 20;
        $maxAttempts = 3;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Api-Key ' . $this->apiKey(),
                    'Accept' => 'application/json',
                ])->timeout($timeout)->get($url);

                $body = $response->json();

                $result = $body['result'] ?? null;
                $state = $body['state'] ?? null;
                $resultLower = is_string($result) ? strtolower($result) : '';
                $stateLower = is_string($state) ? strtolower($state) : '';

                $isSuccess = $resultLower === 'success' || $stateLower === 'paid';
                $isFailed = in_array($resultLower, ['failed', 'canceled', 'error'], true);

                $amount = isset($body['amount']) ? (float) $body['amount'] : null;
                $currency = $body['currency_code'] ?? null;

                $pgParams = $body['pg_params'] ?? [];

                return [
                    'gateway_status_raw' => $result ?? $state,
                    'is_success' => $isSuccess,
                    'is_failed' => $isFailed,
                    'amount' => $amount,
                    'currency' => $currency ? (string) $currency : null,
                    'track_id' => $sessionId,
                    'receipt_id' => $pgParams['receipt_no'] ?? null,
                    'payment_id' => $pgParams['payment_id'] ?? null,
                    'tran_id' => $pgParams['transaction_id'] ?? $pgParams['ref'] ?? null,
                    'requested_order_id' => $body['order_no'] ?? null,
                ];
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
     * Verify webhook signature using HMAC-SHA256.
     * Returns true if the computed signature matches the one in the payload.
     */
    public function verifySignature(array $payload): bool
    {
        $hmacKey = config('services.ottu.hmac_key');
        if (empty($hmacKey)) {
            Log::warning('Ottu HMAC key not configured, skipping signature verification');
            return true;
        }

        $receivedSignature = $payload['signature'] ?? null;
        if (empty($receivedSignature)) {
            return false;
        }

        $signatureFields = [
            'amount',
            'currency_code',
            'customer_first_name',
            'customer_last_name',
            'customer_email',
            'customer_phone',
            'customer_address_line1',
            'customer_address_line2',
            'customer_address_city',
            'customer_address_state',
            'customer_address_country',
            'customer_address_postal_code',
            'gateway_name',
            'gateway_account',
            'order_no',
            'reference_number',
            'result',
            'state',
        ];

        $filtered = [];
        foreach ($signatureFields as $key) {
            if (isset($payload[$key]) && $payload[$key] !== '') {
                $filtered[$key] = $payload[$key];
            }
        }

        ksort($filtered);

        $message = '';
        foreach ($filtered as $k => $v) {
            $message .= $k . $v;
        }

        $computed = hash_hmac('sha256', $message, $hmacKey);

        return hash_equals($computed, $receivedSignature);
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
            'credit-card' => 'credit-card',
            'knet' => 'knet',
            default => $s,
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
            return 'credit-card';
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
