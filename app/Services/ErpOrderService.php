<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ErpOrderService
{
    private string $baseUrl;
    private string $username;
    private string $password;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl  = rtrim(config('services.erp.url', ''), '/');
        $this->username = config('services.erp.username', '');
        $this->password = config('services.erp.password', '');
        $this->timeout  = (int) config('services.erp.timeout', 30);
    }

    /**
     * Send an order to the ERP via GET /API/Order/SendOrder (same URL as configured ERP base; order data as query string).
     *
     * @param  Order  $order  Must have items.variant and invoice loaded (or will be eager-loaded here).
     * @return array  ['success' => bool, 'status' => int|null, 'body' => mixed, 'error' => string|null]
     */
    public function sendOrder(Order $order): array
    {
        if (!$order->relationLoaded('items')) {
            $order->load('items.variant');
        } elseif ($order->items->isNotEmpty() && !$order->items->first()->relationLoaded('variant')) {
            $order->load('items.variant');
        }

        if (!$order->relationLoaded('invoice')) {
            $order->load('invoice');
        }

        if (!$order->relationLoaded('customer')) {
            $order->load('customer');
        }

        if (!$order->relationLoaded('charity')) {
            $order->load('charity');
        }

        $query    = $this->buildPayload($order);
        $endpoint = $this->buildEndpoint('/API/Order/SendOrder');

        return $this->request($endpoint, [
            'query' => $query,
            'log_context' => [
                'action'       => 'SendOrder',
                'order_number' => $order->order_number,
            ],
        ]);
    }

    /**
     * After a new order is created: send to ERP for cash on delivery or wallet only.
     * Logs on failure; does not throw.
     */
    public function dispatchAfterCashOrWalletOrderCreated(Order $order): void
    {
        if (!in_array($order->payment_method, ['cash', 'wallet'], true)) {
            return;
        }

        $order->loadMissing(['items.variant', 'invoice', 'customer', 'charity']);

        $result = $this->sendOrder($order);
        if (!$result['success']) {
            Log::channel('erp')->warning('ERP SendOrder failed after cash/wallet order created', [
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
                'error'        => $result['error'],
                'http_status'  => $result['status'],
            ]);
        }
    }

    /**
     * After an online_link order's invoice is fully paid: send to ERP.
     * Logs on failure; does not throw.
     */
    public function dispatchAfterOnlineInvoicePaid(Order $order): void
    {
        if ($order->payment_method !== 'online_link') {
            return;
        }

        $order->loadMissing(['items.variant', 'invoice', 'customer', 'charity']);

        $invoice = $order->invoice;
        if (!$invoice || $invoice->status !== 'paid') {
            return;
        }

        $result = $this->sendOrder($order);
        if (!$result['success']) {
            Log::channel('erp')->warning('ERP SendOrder failed after online invoice paid', [
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
                'error'        => $result['error'],
                'http_status'  => $result['status'],
            ]);
        }
    }

    /**
     * GET request to the ERP API (same base URL and Basic Auth as SendOrder).
     *
     * @param  string  $path  Path after base URL, e.g. "/API/Order/SomeEndpoint" or "API/Order/SomeEndpoint"
     * @param  array<string, scalar|array|null>  $query  Query string parameters
     * @return array{success: bool, status: int|null, body: mixed, error: string|null}
     */
    public function get(string $path, array $query = []): array
    {
        $endpoint = $this->buildEndpoint($path);

        return $this->request($endpoint, [
            'query' => $query,
            'log_context' => [
                'action' => 'GET',
                'path'   => $path,
                'query'  => $query,
            ],
        ]);
    }

    /**
     * @param  array{query?: array, log_context?: array}  $options
     * @return array{success: bool, status: int|null, body: mixed, error: string|null}
     */
    private function request(string $url, array $options = []): array
    {
        $logContext = array_merge(['method' => 'GET', 'url' => $url], $options['log_context'] ?? []);
        unset($options['log_context']);

        try {
            $pending = Http::withBasicAuth($this->username, $this->password)
                ->timeout($this->timeout)
                ->acceptJson();

            $response = $pending->get($url, $options['query'] ?? []);

            $success = $response->successful();

            Log::channel('erp')->info('ERP ' . ($logContext['action'] ?? 'GET'), array_merge($logContext, [
                'http_status' => $response->status(),
                'success'     => $success,
                'response'    => $response->json() ?? $response->body(),
            ]));

            return [
                'success' => $success,
                'status'  => $response->status(),
                'body'    => $response->json() ?? $response->body(),
                'error'   => $success ? null : 'ERP responded with HTTP ' . $response->status(),
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::channel('erp')->error('ERP connection error', array_merge($logContext, [
                'message' => $e->getMessage(),
            ]));

            return [
                'success' => false,
                'status'  => null,
                'body'    => null,
                'error'   => 'ERP connection error: ' . $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            Log::channel('erp')->error('ERP request error', array_merge($logContext, [
                'message' => $e->getMessage(),
            ]));

            return [
                'success' => false,
                'status'  => null,
                'body'    => null,
                'error'   => $e->getMessage(),
            ];
        }
    }

    private function buildEndpoint(string $path): string
    {
        $path = '/' . ltrim($path, '/');

        return $this->baseUrl . $path;
    }

    /**
     * Build the ERP payload from the order.
     */
    private function buildPayload(Order $order): array
    {
        $invoice      = $order->invoice;
        $deliveryFee  = $invoice ? (float) $invoice->delivery_fee : 0.00;
        $orderDate    = $order->created_at ? $order->created_at->toDateString() : now()->toDateString();
        $deliveryDate = $order->delivery_date ? $order->delivery_date->toDateString() : $orderDate;

        return [
            'OrderNumber'   => $order->order_number,
            'OrderDate'     => $orderDate,
            'DeliveryDate'  => $deliveryDate,
            'DeliveryValue' => round($deliveryFee, 3),
            'CustomerCode'  => $this->resolveCustomerCode($order),
            'allItems'      => $this->buildItems($order),
        ];
    }

    /**
     * Build the allItems array from order items.
     */
    private function buildItems(Order $order): array
    {
        return $order->items->map(function ($item) {
            // itemCode: prefer the variant's short_item (ERP item code), fall back to sku
            $itemCode = $item->variant?->short_item ?? $item->sku ?? '';

            return [
                'itemCode'       => $itemCode,
                'uom'            => 'EA',
                'price'          => round((float) $item->unit_price, 3),
                'quantity'       => (int) $item->quantity,
                'isFOC'          => false,
                'discountAmount' => round((float) $item->discount, 3),
                'taxAmount'      => round((float) $item->tax, 3),
            ];
        })->values()->toArray();
    }

    /**
     * Resolve the ERP customer code from settings based on the order's payment method.
     *
     * Setting keys: cash_customer_code, wallet_customer_code, knet_customer_code, credit_card_customer_code
     */
    private function resolveCustomerCode(Order $order): string
    {
        $method = $order->payment_method;
        $src    = $order->payment_gateway_src ?? '';

        if ($method === 'online_link') {
            $settingKey = str_contains(strtolower($src), 'knet')
                ? 'knet_customer_code'
                : 'credit_card_customer_code';
        } elseif ($method === 'wallet') {
            $settingKey = 'wallet_customer_code';
        } else {
            // cash or any unrecognised method
            $settingKey = 'cash_customer_code';
        }

        return (string) Setting::getValue($settingKey, '');
    }
}
