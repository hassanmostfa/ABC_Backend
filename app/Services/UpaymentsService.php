<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Models\Payment;

class UpaymentsService
{
    public function createPayment(Order $order, float $amount): string
    {
        // Load customer and items
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

        $apiUrl = rtrim(config('services.upayments.url'), '/');
        
        // Check if URL already contains /api/v1, if so, use it directly
        if (str_ends_with($apiUrl, '/api/v1')) {
            $endpoint = $apiUrl . '/charge';
        } else {
            // Otherwise, assume base URL and add /api/v1/charge
            $endpoint = $apiUrl . '/api/v1/charge';
        }

        // Build products array from order items
        $products = $order->items->map(function ($item) {
            return [
                'name' => $item->name,
                'description' => $item->name, // Use name as description if no separate description field
                'price' => (float) $item->unit_price,
                'quantity' => (int) $item->quantity,
            ];
        })->toArray();

        $payload = [
            'products' => $products,
            'order' => [
                'id' => $order->order_number,
                'reference' => (string) $order->id,
                'description' => 'Payment for order #' . $order->order_number,
                'currency' => 'KWD',
                'amount' => (float) $amount,
            ],
            'language' => 'en',
            'reference' => [
                'id' => (string) $order->id,
            ],
            'customer' => [
                'uniqueId' => (string) $customer->id,
                'name' => $customer->name,
                'email' => $customer->email ?? $customer->phone . '@example.com',
                'mobile' => $customer->phone,
            ],
            'returnUrl' => route('payments.success'),
            'cancelUrl' => route('payments.cancel'),
            'notificationUrl' => route('payments.notification'),
        ];

        $gatewaySrc = config('services.upayments.payment_gateway_src');
        if ($gatewaySrc) {
            $payload['paymentGateway'] = ['src' => $gatewaySrc];
            if ($gatewaySrc === 'create-invoice') {
                $payload['notificationType'] = config('services.upayments.notification_type', 'link');
            }
        }

        Log::info('Upayments request', [
            'endpoint' => $endpoint,
            'payload'  => $payload,
        ]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.upayments.key'), // Bearer token as per documentation
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ])->post($endpoint, $payload);

        Log::info('Upayments response', [
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);

        if (!$response->successful()) {
            $message = $response->json()['message'] ?? 'Upayments request failed';
            throw new \Exception($message);
        }

        $data = $response->json();
        $inner = $data['data'] ?? $data;

        $link = $this->extractPaymentLinkFromResponse($inner);
        if ($link !== null) {
            return $link;
        }

        if (config('services.upayments.logging_enabled', true)) {
            Log::warning('Upayments charge response had no payment URL', ['data' => $data]);
        }
        throw new \Exception('Payment link not found in Upayments response');
    }

    /**
     * Extract payment URL from API response. Ignores numeric values (e.g. invoice_id in "link" field).
     */
    protected function extractPaymentLinkFromResponse(array $data): ?string
    {
        $candidates = [
            $data['url'] ?? null,
            $data['link'] ?? null,
            $data['payment_url'] ?? null,
            $data['invoice_url'] ?? null,
            $data['redirect_url'] ?? null,
            $data['payment_link'] ?? null,
            $data['redirectUrl'] ?? null,
            $data['session_url'] ?? null,
        ];

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
     * Create payment link for wallet charge (top-up)
     */
    public function createWalletChargePayment(Payment $payment, float $amount): string
    {
        $payment->load('customer');
        $customer = $payment->customer;
        if (!$customer) {
            throw new \Exception('Customer is required for wallet charge payment');
        }

        $apiUrl = rtrim(config('services.upayments.url'), '/');
        if (str_ends_with($apiUrl, '/api/v1')) {
            $endpoint = $apiUrl . '/charge';
        } else {
            $endpoint = $apiUrl . '/api/v1/charge';
        }

        $products = [
            [
                'name' => 'Wallet Top-up',
                'description' => 'Wallet balance charge - ' . number_format($amount, 2) . ' KWD (+ bonus)',
                'price' => (float) $amount,
                'quantity' => 1,
            ],
        ];

        $payload = [
            'products' => $products,
            'order' => [
                'id' => $payment->reference,
                'reference' => (string) $payment->id,
                'description' => 'Wallet charge - ' . $payment->reference,
                'currency' => 'KWD',
                'amount' => (float) $amount,
            ],
            'language' => 'en',
            'reference' => [
                'id' => (string) $payment->id,
            ],
            'customer' => [
                'uniqueId' => (string) $customer->id,
                'name' => $customer->name,
                'email' => $customer->email ?? $customer->phone . '@example.com',
                'mobile' => $customer->phone,
            ],
            'returnUrl' => route('payments.wallet-charge.success'),
            'cancelUrl' => route('payments.wallet-charge.cancel'),
            'notificationUrl' => route('payments.wallet-charge.notification'),
        ];

        $gatewaySrc = config('services.upayments.payment_gateway_src');
        if ($gatewaySrc) {
            $payload['paymentGateway'] = ['src' => $gatewaySrc];
            if ($gatewaySrc === 'create-invoice') {
                $payload['notificationType'] = config('services.upayments.notification_type', 'link');
            }
        }

        Log::info('Upayments wallet charge request', [
            'endpoint' => $endpoint,
            'wallet_charge_reference' => $payment->reference,
        ]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.upayments.key'),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post($endpoint, $payload);

        Log::info('Upayments wallet charge response', [
            'status' => $response->status(),
            'body' => $response->json(),
        ]);

        if (!$response->successful()) {
            $message = $response->json()['message'] ?? 'Upayments request failed';
            throw new \Exception($message);
        }

        $data = $response->json();
        $inner = $data['data'] ?? $data;
        if (!is_array($inner)) {
            if (config('services.upayments.logging_enabled', true)) {
                Log::warning('Upayments wallet charge response had no data', ['data' => $data]);
            }
            throw new \Exception('Payment link not found in Upayments response');
        }

        $link = $this->extractPaymentLinkFromResponse($inner);
        if ($link !== null) {
            return $link;
        }

        if (config('services.upayments.logging_enabled', true)) {
            Log::warning('Upayments wallet charge response had no payment URL', ['data' => $data]);
        }
        throw new \Exception('Payment link not found in Upayments response');
    }

    /**
     * Get payment status from Upayments (single source of truth for webhook verification).
     * Timeout 20s, retry 2 times.
     *
     * @return array{gateway_status_raw: mixed, is_success: bool, is_failed: bool, amount: float|null, currency: string|null, track_id: string, receipt_id: string|null, payment_id: string|null, tran_id: string|null, requested_order_id: string|null}
     */
    public function getPaymentStatus(string $trackId): array
    {
        $baseUrl = rtrim(config('services.upayments.url'), '/');
        $statusPath = '/' . ltrim(config('services.upayments.status_endpoint', '/api/v1/getpaymentstatus'), '/');
        $url = $baseUrl . $statusPath . (str_contains($statusPath, '?') ? '&' : '?') . 'track_id=' . urlencode($trackId);

        $timeout = 20;
        $maxAttempts = 3;

        $lastException = null;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . config('services.upayments.key'),
                    'Accept' => 'application/json',
                ])->timeout($timeout)->get($url);

                $body = $response->json();
                $gatewayStatusRaw = $body['result'] ?? $body['status'] ?? $body['data']['result'] ?? $body['data']['status'] ?? null;
                $statusLower = is_string($gatewayStatusRaw) ? strtolower($gatewayStatusRaw) : '';

                $isSuccess = in_array($statusLower, ['captured', 'success', 'paid', 'completed', 'approved'], true);
                $isFailed = in_array($statusLower, ['failed', 'rejected', 'declined', 'cancelled', 'canceled'], true);

                $data = $body['data'] ?? $body;
                $amount = isset($data['amount']) ? (float) $data['amount'] : (isset($body['amount']) ? (float) $body['amount'] : null);
                $currency = $data['currency'] ?? $body['currency'] ?? null;

                return [
                    'gateway_status_raw' => $gatewayStatusRaw,
                    'is_success' => $isSuccess,
                    'is_failed' => $isFailed,
                    'amount' => $amount,
                    'currency' => $currency ? (string) $currency : null,
                    'track_id' => $trackId,
                    'receipt_id' => $data['receipt_id'] ?? $body['receipt_id'] ?? null,
                    'payment_id' => $data['payment_id'] ?? $body['payment_id'] ?? null,
                    'tran_id' => $data['tran_id'] ?? $body['tran_id'] ?? null,
                    'requested_order_id' => $data['requested_order_id'] ?? $body['requested_order_id'] ?? $data['order_id'] ?? $body['order_id'] ?? null,
                ];
            } catch (\Exception $e) {
                $lastException = $e;
                if (config('services.upayments.logging_enabled', true)) {
                    Log::warning('Upayments getPaymentStatus attempt failed', [
                        'track_id' => $trackId,
                        'attempt' => $attempt,
                        'message' => $e->getMessage(),
                    ]);
                }
                if ($attempt < $maxAttempts) {
                    usleep(500000);
                }
            }
        }

        throw $lastException ?? new \Exception('Upayments getPaymentStatus failed');
    }
}
