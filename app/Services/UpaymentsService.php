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
            'returnUrl' => route('payments.success', ['order_id' => $order->id]),
            'cancelUrl' => route('payments.cancel', ['order_id' => $order->id]),
            'notificationUrl' => route('payments.notification', ['order_id' => $order->id]),
        ];

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

        // Default payment gateway returns 'link', create-invoice returns 'url'
        if (isset($data['data']['link'])) {
            return $data['data']['link'];
        }

        if (isset($data['data']['url'])) {
            return $data['data']['url'];
        }

        throw new \Exception('Payment link not found in Upayments response');
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
            'returnUrl' => route('payments.wallet-charge.success', ['reference' => $payment->reference]),
            'cancelUrl' => route('payments.wallet-charge.cancel', ['reference' => $payment->reference]),
            'notificationUrl' => route('payments.wallet-charge.notification', ['reference' => $payment->reference]),
        ];

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
        if (isset($data['data']['link'])) {
            return $data['data']['link'];
        }
        if (isset($data['data']['url'])) {
            return $data['data']['url'];
        }

        throw new \Exception('Payment link not found in Upayments response');
    }
}
