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

    private const ERP_PRICE_DECIMALS = 6;

    /** @var list<string> */
    private const ERP_PRICE_FIELDS = ['DeliveryValue', 'NetTotal', 'GrossTotal', 'TotalTax', 'TotalDiscount', 'price', 'discountAmount', 'taxAmount'];

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

        return $this->request($endpoint, $this->buildSendOrderRequestOptions($payload, [
            'action'       => 'SendOrder',
            'order_number' => $order->order_number,
            'items_count'  => is_array($payload['allItems'] ?? null) ? count($payload['allItems']) : 0,
        ]));
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

        return $this->request($endpoint, $this->buildSendOrderRequestOptions($payload, [
            'action'       => 'SendOrderRaw',
            'order_number' => $payload['OrderNumber'] ?? null,
            'items_count'  => is_array($payload['allItems'] ?? null) ? count($payload['allItems']) : 0,
        ]));
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

        $query       = $options['query'] ?? [];
        $json        = $options['json'] ?? null;
        $body        = $options['body'] ?? null;
        $logPayload  = $options['log_payload'] ?? $json;
        unset($options['method'], $options['query'], $options['json'], $options['body'], $options['log_payload']);

        $maxAttempts = max(1, $this->retries + 1);

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $requestStats = [];

            try {
                if ($this->shouldLogFailedPayload($method, $logPayload, $body)) {
                    Log::channel('erp')->info('ERP outbound payload', array_merge($logContext, [
                        'attempt'      => $attempt,
                        'max_attempts' => $maxAttempts,
                        'payload'      => $logPayload,
                        'payload_wire' => is_string($body) ? $body : null,
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

                if ($method === 'POST' && is_string($body)) {
                    $response = $pending->withBody($body, 'application/json')->post($url);
                } elseif ($method === 'POST' && is_array($json)) {
                    $response = $pending->asJson()->post($url, $json);
                } else {
                    $response = $pending->get($url, $query);
                }

                $responseBody = $response->json() ?? $response->body();
                $businessResult = $this->evaluateErpBusinessResponse($response->status(), $responseBody);
                $success = $businessResult['success'];

                Log::channel('erp')->info('ERP ' . ($logContext['action'] ?? 'GET'), array_merge($logContext, [
                    'http_status'   => $response->status(),
                    'success'       => $success,
                    'attempt'       => $attempt,
                    'max_attempts'  => $maxAttempts,
                    'stats'         => $requestStats,
                    'response'      => $responseBody,
                ]));

                return [
                    'success' => $success,
                    'status'  => $response->status(),
                    'body'    => $responseBody,
                    'error'   => $businessResult['error'],
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
                    if ($this->shouldLogFailedPayload($method, $logPayload, $body)) {
                        Log::channel('erp')->warning('ERP final failed payload', array_merge($logContext, [
                            'attempt'      => $attempt,
                            'max_attempts' => $maxAttempts,
                            'payload'      => $logPayload,
                            'payload_wire' => is_string($body) ? $body : null,
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

    private function shouldLogFailedPayload(string $method, mixed $payload, ?string $body = null): bool
    {
        return $this->logFailedPayload && $method === 'POST' && (is_array($payload) || is_string($body));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $logContext
     * @return array{method: string, body: string, log_payload: array<string, mixed>, log_context: array<string, mixed>}
     */
    private function buildSendOrderRequestOptions(array $payload, array $logContext): array
    {
        $body = $this->encodeOrderPayloadJson($payload);

        return [
            'method'      => 'POST',
            'body'        => $body,
            'log_payload' => $payload,
            'log_context' => array_merge($logContext, [
                'payload_bytes' => strlen($body),
            ]),
        ];
    }

    /**
     * Encode order payload so price fields appear as JSON numbers with 4 decimals (e.g. 0.7500).
     *
     * @param  array<string, mixed>  $payload
     */
    private function encodeOrderPayloadJson(array $payload): string
    {
        return $this->encodeJsonValue($payload);
    }

    private function encodeJsonValue(mixed $value, ?string $key = null): string
    {
        if (is_array($value)) {
            if ($value === []) {
                return '[]';
            }

            if (array_is_list($value)) {
                return '[' . implode(',', array_map(fn ($item) => $this->encodeJsonValue($item), $value)) . ']';
            }

            $pairs = [];
            foreach ($value as $entryKey => $entryValue) {
                $pairs[] = json_encode((string) $entryKey, JSON_UNESCAPED_UNICODE)
                    . ':' . $this->encodeJsonValue($entryValue, (string) $entryKey);
            }

            return '{' . implode(',', $pairs) . '}';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if ($key !== null && in_array($key, self::ERP_PRICE_FIELDS, true) && is_numeric($value)) {
            return number_format(round((float) $value, self::ERP_PRICE_DECIMALS), self::ERP_PRICE_DECIMALS, '.', '');
        }

        if (is_int($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE) ?: 'null';
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

        $amountDue      = $invoice ? (float) $invoice->amount_due : 0.00;
        $taxAmount      = $invoice ? (float) $invoice->tax_amount : 0.00;

        return [
            'OrderNumber'   => $order->order_number,
            'OrderDate'     => $orderDate,
            'DeliveryDate'  => $deliveryDate,
            'DeliveryValue' => $this->formatErpPrice($deliveryFee),
            'CustomerCode'  => $this->resolveCustomerCode($order),
            'EmployeeCode'  => $this->resolveEmployeeCode($order),
            'LPO'           => $this->resolveLpo($order),
            'Notes'         => $this->resolveNotes($order),
            'NetTotal'      => $this->formatErpPrice($amountDue - $taxAmount),
            'GrossTotal'    => $this->formatErpPrice($amountDue),
            'TotalTax'      => $this->formatErpPrice($taxAmount),
            'TotalDiscount' => $this->formatErpPrice(0),
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
     * with payment type appended. Labels and location names are in Arabic.
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

        if ($governorateName = $this->localizedArabicName($addr->governorate)) {
            $parts[] = $governorateName;
        }
        if ($areaName = $this->localizedArabicName($addr->area)) {
            $parts[] = $areaName;
        }
        if (!empty($addr->type)) {
            $parts[] = $this->translateAddressType((string) $addr->type);
        }
        if (!empty($addr->building_name)) {
            $parts[] = (string) $addr->building_name;
        }
        if (!empty($addr->apartment_number)) {
            $parts[] = 'شقة ' . $addr->apartment_number;
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
            $parts[] = 'قطعة ' . $addr->block;
        }

        $addressText = implode(' | ', array_filter($parts));

        $customerPhone = trim((string) ($order->customer?->phone ?? ''));
        if ($customerPhone !== '') {
            $addressText = $addressText !== ''
                ? $addressText . ' | هاتف: ' . $customerPhone
                : 'هاتف: ' . $customerPhone;
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

        $userNote = trim((string) ($order->note ?? ''));
        if ($userNote !== '') {
            $baseNotes = $baseNotes !== '' ? $baseNotes . ' | ' . $userNote : $userNote;
        }

        if ($paymentType !== '') {
            return $baseNotes !== '' ? $baseNotes . ' | الدفع: ' . $paymentType : 'الدفع: ' . $paymentType;
        }

        return $baseNotes;
    }

    private function resolvePaymentType(Order $order): string
    {
        $method = strtolower(trim((string) ($order->payment_method ?? '')));

        if ($method === 'cash') {
            return 'نقدي';
        }

        if ($method === 'wallet') {
            return 'محفظة';
        }

        if ($method === 'online_link') {
            return 'إلكتروني';
        }

        return '';
    }

    private function localizedArabicName(?object $model): string
    {
        if ($model === null) {
            return '';
        }

        $arabic = trim((string) ($model->name_ar ?? ''));

        return $arabic !== '' ? $arabic : trim((string) ($model->name_en ?? ''));
    }

    private function translateAddressType(string $type): string
    {
        return match (strtolower(trim($type))) {
            'apartment' => 'شقة',
            'house' => 'منزل',
            'office' => 'مكتب',
            default => $type,
        };
    }

    /**
     * Build the allItems array from order items (one row per itemCode + uom).
     */
    private function buildItems(Order $order): array
    {
        $grouped = [];

        foreach ($order->items as $item) {
            $itemCode = $item->variant?->sku ?? '';
            $uom = $item->variant?->short_item ?? '';
            $key = $itemCode . '|' . $uom;
            $quantity = (int) $item->quantity;
            $netLineTotal = max(0, (float) $item->total_price - (float) $item->discount);
            $taxAmount = (float) $item->tax;

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'itemCode' => $itemCode,
                    'uom' => $uom,
                    'quantity' => 0,
                    'netLineTotal' => 0.0,
                    'taxAmount' => 0.0,
                ];
            }

            $grouped[$key]['quantity'] += $quantity;
            $grouped[$key]['netLineTotal'] += $netLineTotal;
            $grouped[$key]['taxAmount'] += $taxAmount;
        }

        return collect($grouped)->map(function (array $row) {
            $quantity = (int) $row['quantity'];
            $netLineTotal = $this->formatErpPrice($row['netLineTotal']);
            $unitPriceAfterDiscount = $quantity > 0
                ? $this->formatErpPrice($netLineTotal / $quantity)
                : 0.0;

            return [
                'itemCode'       => $row['itemCode'],
                'uom'            => $row['uom'],
                'price'          => $unitPriceAfterDiscount,
                'quantity'       => $quantity,
                'isFOC'          => false,
                'discountAmount' => $this->formatErpPrice(0),
                'taxAmount'      => $this->formatErpPrice($row['taxAmount']),
            ];
        })->values()->toArray();
    }

    /**
     * ERP may return HTTP 200 with a business status (e.g. status: -1) indicating failure.
     *
     * @return array{success: bool, error: string|null}
     */
    private function evaluateErpBusinessResponse(int $httpStatus, mixed $responseBody): array
    {
        if ($httpStatus < 200 || $httpStatus >= 300) {
            return [
                'success' => false,
                'error' => 'ERP responded with HTTP ' . $httpStatus,
            ];
        }

        if (!is_array($responseBody) || !array_key_exists('status', $responseBody)) {
            return ['success' => true, 'error' => null];
        }

        $businessStatus = $responseBody['status'];
        if (is_numeric($businessStatus) && (int) $businessStatus < 0) {
            $message = is_string($responseBody['message'] ?? null) && $responseBody['message'] !== ''
                ? $responseBody['message']
                : 'ERP returned status ' . $businessStatus;

            return [
                'success' => false,
                'error' => 'ERP rejected request: ' . $message,
            ];
        }

        return ['success' => true, 'error' => null];
    }

    /**
     * Round monetary values to 6 decimal places for ERP payloads.
     * Must be a JSON number (not string) — ERP deserializes to System.Decimal.
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
        foreach (['DeliveryValue', 'NetTotal', 'GrossTotal', 'TotalTax', 'TotalDiscount'] as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = $this->formatErpPrice($payload[$field]);
            }
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
