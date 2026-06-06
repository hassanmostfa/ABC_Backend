<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Order;
use App\Models\Setting;
use App\Support\KuwaitPhone;
use GuzzleHttp\TransferStats;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ErpOrderService
{
    public const DEFAULT_EMPLOYEE_CODE = '200992';

    private const ERP_PRICE_DECIMALS = 4;

    private string $baseUrl;
    private string $username;
    private string $password;
    private int $timeout;
    private int $connectTimeout;
    private int $retries;
    private int $retrySleepMs;
    private bool $logFailedPayload;

    public function __construct()
    {
        $this->baseUrl  = rtrim(config('services.erp.url', ''), '/');
        $this->username = config('services.erp.username', '');
        $this->password = config('services.erp.password', '');
        $this->timeout  = (int) config('services.erp.timeout', 30);
        $this->connectTimeout = (int) config('services.erp.connect_timeout', 10);
        $this->retries = (int) config('services.erp.retries', 2);
        $this->retrySleepMs = (int) config('services.erp.retry_sleep_ms', 1000);
        $this->logFailedPayload = (bool) config('services.erp.log_failed_payload', false);
    }

    /**
     * Send an order to the ERP via POST /API/Order/SendOrder (same URL as configured ERP base; order data as JSON body).
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

        $order->loadMissing([
            'invoice.payments',
            'customerAddress.country',
            'customerAddress.governorate',
            'customerAddress.area',
            'createdBy',
        ]);

        $payload  = $this->normalizeOrderPayloadPrices($this->buildPayload($order));
        $endpoint = $this->buildEndpoint('/API/Order/SendOrder');

        return $this->request($endpoint, [
            'method' => 'POST',
            'json'   => $payload,
            'log_context' => [
                'action'       => 'SendOrder',
                'order_number' => $order->order_number,
                'items_count'  => is_array($payload['allItems'] ?? null) ? count($payload['allItems']) : 0,
                'payload_bytes' => strlen((string) json_encode($payload)),
            ],
        ]);
    }

    /**
     * Send a raw order payload directly to ERP SendOrder endpoint.
     *
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, status: int|null, body: mixed, error: string|null}
     */
    public function sendRawOrder(array $payload): array
    {
        $endpoint = $this->buildEndpoint('/API/Order/SendOrder');
        $payload = $this->normalizeOrderPayloadPrices($payload);

        return $this->request($endpoint, [
            'method' => 'POST',
            'json'   => $payload,
            'log_context' => [
                'action'        => 'SendOrderRaw',
                'order_number'  => $payload['OrderNumber'] ?? null,
                'items_count'   => is_array($payload['allItems'] ?? null) ? count($payload['allItems']) : 0,
                'payload_bytes' => strlen((string) json_encode($payload)),
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
        } else {
            $order->update(['is_sent_to_erp' => true]);
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
        } else {
            $order->update(['is_sent_to_erp' => true]);
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
     * @param  array{method?: string, query?: array, json?: array, log_context?: array}  $options
     * @return array{success: bool, status: int|null, body: mixed, error: string|null}
     */
    private function request(string $url, array $options = []): array
    {
        $method = strtoupper($options['method'] ?? 'GET');
        $logContext = array_merge(['method' => $method, 'url' => $url], $options['log_context'] ?? []);
        unset($options['log_context']);

        $query = $options['query'] ?? [];
        $json  = $options['json'] ?? null;
        unset($options['method'], $options['query'], $options['json']);

        $maxAttempts = max(1, $this->retries + 1);

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $requestStats = [];

            try {
                if ($this->shouldLogFailedPayload($method, $json)) {
                    Log::channel('erp')->info('ERP outbound payload', array_merge($logContext, [
                        'attempt'      => $attempt,
                        'max_attempts' => $maxAttempts,
                        'payload'      => $json,
                    ]));
                }

                $pending = Http::withBasicAuth($this->username, $this->password)
                    ->connectTimeout($this->connectTimeout)
                    ->timeout($this->timeout)
                    ->withHeaders([
                        'Connection' => 'close',
                        'Expect' => '',
                    ])
                    ->withOptions([
                        'version' => 1.1,
                        'curl' => [
                            CURLOPT_FORBID_REUSE => true,
                            CURLOPT_FRESH_CONNECT => true,
                        ],
                        'on_stats' => function (TransferStats $stats) use (&$requestStats): void {
                            $handlerStats = $stats->getHandlerStats();

                            $requestStats = [
                                'effective_uri'      => (string) $stats->getEffectiveUri(),
                                'total_time_sec'     => $stats->getTransferTime(),
                                'primary_ip'         => $handlerStats['primary_ip'] ?? null,
                                'primary_port'       => $handlerStats['primary_port'] ?? null,
                                'local_ip'           => $handlerStats['local_ip'] ?? null,
                                'local_port'         => $handlerStats['local_port'] ?? null,
                                'connect_time_sec'   => $handlerStats['connect_time'] ?? null,
                                'starttransfer_sec'  => $handlerStats['starttransfer_time'] ?? null,
                                'pretransfer_sec'    => $handlerStats['pretransfer_time'] ?? null,
                                'uploaded_bytes'     => $handlerStats['size_upload'] ?? null,
                                'downloaded_bytes'   => $handlerStats['size_download'] ?? null,
                            ];
                        },
                    ])
                    ->acceptJson();

                if ($method === 'POST' && is_array($json)) {
                    $response = $pending->asJson()->post($url, $json);
                } else {
                    $response = $pending->get($url, $query);
                }

                $success = $response->successful();

                Log::channel('erp')->info('ERP ' . ($logContext['action'] ?? 'GET'), array_merge($logContext, [
                    'http_status'   => $response->status(),
                    'success'       => $success,
                    'attempt'       => $attempt,
                    'max_attempts'  => $maxAttempts,
                    'stats'         => $requestStats,
                    'response'      => $response->json() ?? $response->body(),
                ]));

                return [
                    'success' => $success,
                    'status'  => $response->status(),
                    'body'    => $response->json() ?? $response->body(),
                    'error'   => $success ? null : 'ERP responded with HTTP ' . $response->status(),
                ];
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $isLastAttempt = $attempt >= $maxAttempts;

                Log::channel('erp')->{$isLastAttempt ? 'error' : 'warning'}(
                    $isLastAttempt ? 'ERP connection error' : 'ERP connection error, retrying',
                    array_merge($logContext, [
                        'attempt'      => $attempt,
                        'max_attempts' => $maxAttempts,
                        'stats'        => $requestStats,
                        'message'      => $e->getMessage(),
                    ])
                );

                if ($isLastAttempt) {
                    if ($this->shouldLogFailedPayload($method, $json)) {
                        Log::channel('erp')->warning('ERP final failed payload', array_merge($logContext, [
                            'attempt'      => $attempt,
                            'max_attempts' => $maxAttempts,
                            'payload'      => $json,
                        ]));
                    }

                    return [
                        'success' => false,
                        'status'  => null,
                        'body'    => null,
                        'error'   => 'ERP connection error: ' . $e->getMessage(),
                    ];
                }

                if ($this->retrySleepMs > 0) {
                    usleep($this->retrySleepMs * 1000);
                }
            } catch (\Throwable $e) {
                Log::channel('erp')->error('ERP request error', array_merge($logContext, [
                    'attempt'      => $attempt,
                    'max_attempts' => $maxAttempts,
                    'stats'        => $requestStats,
                    'message'      => $e->getMessage(),
                ]));

                return [
                    'success' => false,
                    'status'  => null,
                    'body'    => null,
                    'error'   => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => false,
            'status'  => null,
            'body'    => null,
            'error'   => 'ERP request failed unexpectedly before execution.',
        ];
    }

    private function shouldLogFailedPayload(string $method, mixed $json): bool
    {
        return $this->logFailedPayload && $method === 'POST' && is_array($json);
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
            'DeliveryValue' => $this->formatErpPrice($deliveryFee),
            'CustomerCode'  => $this->resolveCustomerCode($order),
            'EmployeeCode'  => $this->resolveEmployeeCode($order),
            'LPO'           => $this->resolveLpo($order),
            'Notes'         => $this->resolveNotes($order),
            'allItems'      => $this->buildItems($order),
        ];
    }

    /**
     * ERP LPO: payment track_id when the invoice is paid (e.g. gateway track ID).
     */
    private function resolveLpo(Order $order): string
    {
        $invoice = $order->invoice;
        if (!$invoice || $invoice->status !== 'paid') {
            return '';
        }

        $payments = $invoice->relationLoaded('payments')
            ? $invoice->payments
            : $invoice->payments()->get();

        $completed = $payments->where('status', 'completed')->sortByDesc('id');
        $withTrackId = $completed->first(function ($p) {
            $t = $p->track_id ?? null;

            return $t !== null && $t !== '';
        });

        if ($withTrackId) {
            return (string) $withTrackId->track_id;
        }

        $latest = $completed->first();

        return $latest && $latest->track_id !== null && $latest->track_id !== ''
            ? (string) $latest->track_id
            : '';
    }

    /**
     * ERP EmployeeCode: admin employee_code when order was created by an admin;
     * app orders use 1000; website orders use 2000.
     */
    private function resolveEmployeeCode(Order $order): string
    {
        $adminId = $this->resolveAdminIdForOrder($order);
        if ($adminId !== null) {
            return $this->getEmployeeCodeByAdminId($adminId);
        }

        $orderNumber = strtoupper(trim((string) ($order->order_number ?? '')));

        if (str_starts_with($orderNumber, 'APP-')) {
            return '1000';
        }

        if (str_starts_with($orderNumber, 'WEB-')) {
            return '2000';
        }

        return self::DEFAULT_EMPLOYEE_CODE;
    }

    private function resolveAdminIdForOrder(Order $order): ?int
    {
        if ($order->created_by_id && $this->isAdminCreatorType($order->created_by_type)) {
            return (int) $order->created_by_id;
        }

        $order->loadMissing('createdBy');
        if ($order->createdBy instanceof Admin) {
            return (int) $order->createdBy->id;
        }

        return null;
    }

    private function isAdminCreatorType(?string $type): bool
    {
        if ($type === null || $type === '') {
            return false;
        }

        return $type === Admin::class || str_ends_with($type, '\\Admin');
    }

    /**
     * Look up the ERP employee code for the given admin ID.
     * Returns the default fallback code when the admin is not found or has no code set.
     */
    public function getEmployeeCodeByAdminId(int $adminId): string
    {
        $admin = Admin::find($adminId);
        $employeeCode = trim((string) ($admin?->employee_code ?? ''));

        return $employeeCode !== '' ? $employeeCode : self::DEFAULT_EMPLOYEE_CODE;
    }

    /**
     * ERP Notes: customer name plus address text (order.address or customer-address detail),
     * with payment type (Cash or Online) appended.
     */
    private function resolveNotes(Order $order): string
    {
        $order->loadMissing('customer');
        $customerName = trim((string) ($order->customer?->name ?? ''));

        $direct = trim((string) ($order->address ?? ''));
        if ($direct !== '') {
            return $this->formatNotes($customerName, $direct, $order);
        }

        $order->loadMissing('customerAddress.country', 'customerAddress.governorate', 'customerAddress.area');
        $addr = $order->customerAddress;
        if (!$addr) {
            return $this->formatNotes($customerName, '', $order);
        }

        $parts = [];

        if ($addr->governorate?->name_en) {
            $parts[] = $addr->governorate->name_en;
        }
        if ($addr->area?->name_en) {
            $parts[] = $addr->area->name_en;
        }
        if (!empty($addr->type)) {
            $parts[] = (string) $addr->type;
        }
        if (!empty($addr->building_name)) {
            $parts[] = (string) $addr->building_name;
        }
        if (!empty($addr->apartment_number)) {
            $parts[] = 'Apt ' . $addr->apartment_number;
        }
        if (!empty($addr->company)) {
            $parts[] = (string) $addr->company;
        }
        if (!empty($addr->street)) {
            $parts[] = (string) $addr->street;
        }
        if (!empty($addr->house)) {
            $parts[] = (string) $addr->house;
        }
        if (!empty($addr->block)) {
            $parts[] = 'Block ' . $addr->block;
        }

        $addressText = implode(' | ', array_filter($parts));

        $customerPhone = trim((string) ($order->customer?->phone ?? ''));
        if ($customerPhone !== '') {
            $addressText = $addressText !== ''
                ? $addressText . ' | Phone: ' . $customerPhone
                : 'Phone: ' . $customerPhone;
        }

        return $this->formatNotes($customerName, $addressText, $order);
    }

    private function formatNotes(string $customerName, string $addressText, Order $order): string
    {
        $paymentType = $this->resolvePaymentType($order);

        $baseParts = [];
        if ($customerName !== '') {
            $baseParts[] = $customerName;
        }
        if ($addressText !== '') {
            $baseParts[] = $addressText;
        }

        $baseNotes = implode(' | ', $baseParts);

        if ($paymentType !== '') {
            return $baseNotes !== '' ? $baseNotes . ' | Payment: ' . $paymentType : 'Payment: ' . $paymentType;
        }

        return $baseNotes;
    }

    private function resolvePaymentType(Order $order): string
    {
        $method = strtolower(trim((string) ($order->payment_method ?? '')));

        if (in_array($method, ['cash', 'wallet'], true)) {
            return 'Cash';
        }

        if ($method === 'online_link') {
            return 'Online';
        }

        return '';
    }

    /**
     * Build the allItems array from order items.
     */
    private function buildItems(Order $order): array
    {
        return $order->items->map(function ($item) {
            $itemCode = $item->variant?->sku ?? '';
            $uom = $item->variant?->short_item ?? '';
            $quantity = (int) $item->quantity;
            $netLineTotal = max(0, (float) $item->total_price - (float) $item->discount);
            $unitPriceAfterDiscount = $quantity > 0
                ? $netLineTotal / $quantity
                : (float) $item->unit_price;

            return [
                'itemCode'       => $itemCode,
                'uom'            => $uom,
                'price'          => $this->formatErpPrice($unitPriceAfterDiscount),
                'quantity'       => $quantity,
                'isFOC'          => false,
                'discountAmount' => $this->formatErpPrice(0),
                'taxAmount'      => $this->formatErpPrice((float) $item->tax),
            ];
        })->values()->toArray();
    }

    /**
     * Round monetary values to 4 decimal places (KWD fils) for ERP payloads.
     */
    private function formatErpPrice(float|int|string|null $value): float
    {
        return round((float) $value, self::ERP_PRICE_DECIMALS);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalizeOrderPayloadPrices(array $payload): array
    {
        if (array_key_exists('DeliveryValue', $payload)) {
            $payload['DeliveryValue'] = $this->formatErpPrice($payload['DeliveryValue']);
        }

        if (!isset($payload['allItems']) || !is_array($payload['allItems'])) {
            return $payload;
        }

        foreach ($payload['allItems'] as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            foreach (['price', 'discountAmount', 'taxAmount'] as $field) {
                if (array_key_exists($field, $item)) {
                    $payload['allItems'][$index][$field] = $this->formatErpPrice($item[$field]);
                }
            }
        }

        return $payload;
    }

    /**
     * ERP CustomerCode: customer phone without country code (same as AddNewCustomer).
     * Falls back to payment-method settings when the order has no customer phone.
     */
    private function resolveCustomerCode(Order $order): string
    {
        $order->loadMissing('customer');
        $customerCode = KuwaitPhone::withoutCountryCode($order->customer?->phone);

        if ($customerCode !== '') {
            return $customerCode;
        }

        $method = $order->payment_method;
        $src    = $order->payment_gateway_src ?? '';

        if ($method === 'online_link') {
            $settingKey = str_contains(strtolower($src), 'knet')
                ? 'knet_customer_code'
                : 'credit_card_customer_code';
        } elseif ($method === 'wallet') {
            $settingKey = 'wallet_customer_code';
        } else {
            $settingKey = 'cash_customer_code';
        }

        return (string) Setting::getValue($settingKey, '');
    }
}
