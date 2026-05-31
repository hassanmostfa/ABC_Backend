<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Support\KuwaitPhone;
use GuzzleHttp\TransferStats;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ErpCustomerService
{
    public const SOURCE_APP = 'APP';
    public const SOURCE_WEB = 'WEB';
    public const SOURCE_CALS = 'CALS';

    private const DEFAULT_EMAIL = 'abdelhamid@abcjuice.com.kw';
    private const DEFAULT_AREA_ID = 1;
    private const DEFAULT_COUNTRY_ID = 1;

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
     * After a new customer is created: send to ERP. Logs on failure; does not throw.
     */
    public function dispatchAfterCustomerCreated(Customer $customer, string $source): void
    {
        $customer->loadMissing([
            'addresses.country',
            'addresses.governorate',
            'addresses.area',
        ]);

        $result = $this->addNewCustomer($customer, $source);
        if (!$result['success']) {
            Log::channel('erp')->warning('ERP AddNewCustomer failed after customer created', [
                'customer_id' => $customer->id,
                'phone'       => $customer->phone,
                'source'      => $source,
                'error'       => $result['error'],
                'http_status' => $result['status'],
            ]);
        }
    }

    /**
     * @return array{success: bool, status: int|null, body: mixed, error: string|null}
     */
    public function addNewCustomer(Customer $customer, string $source): array
    {
        $payload  = $this->buildPayload($customer, $source);
        $endpoint = $this->buildEndpoint('/API/customer/AddNewCustomer');

        return $this->request($endpoint, [
            'method' => 'POST',
            'json'   => $payload,
            'log_context' => [
                'action'        => 'AddNewCustomer',
                'customer_id'   => $customer->id,
                'customer_code' => $payload['CustomerCode'] ?? null,
                'source'        => $source,
                'payload_bytes' => strlen((string) json_encode($payload)),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Customer $customer, string $source): array
    {
        $phoneWithoutCode = KuwaitPhone::withoutCountryCode($customer->phone);
        $email = trim((string) ($customer->email ?? ''));

        return [
            'Name'         => (string) $customer->name,
            'CustomerCode' => $phoneWithoutCode,
            'email'        => $email !== '' ? $email : self::DEFAULT_EMAIL,
            'source'       => $source,
            'DOB'          => ($customer->created_at ?? now())->toDateString(),
            'AreaId'       => self::DEFAULT_AREA_ID,
            'postcode'     => '000000',
            'Currency'     => 'KWD',
            'GroupCode'    => '00010-02-05',
            'numbers'      => $this->buildNumbers($customer, $phoneWithoutCode),
            'addresses'    => $this->buildAddresses($customer),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildNumbers(Customer $customer, string $primaryNumber): array
    {
        $numbers = [[
            'number'     => $primaryNumber,
            'is_Default' => true,
            'CountryId'  => self::DEFAULT_COUNTRY_ID,
        ]];

        $secondaryNumber = $this->resolveSecondaryNumber($customer, $primaryNumber);
        if ($secondaryNumber !== null) {
            $numbers[] = [
                'number'     => $secondaryNumber,
                'is_Default' => false,
                'CountryId'  => self::DEFAULT_COUNTRY_ID,
            ];
        }

        return $numbers;
    }

    private function resolveSecondaryNumber(Customer $customer, string $primaryNumber): ?string
    {
        foreach ($customer->addresses as $address) {
            $addressPhone = KuwaitPhone::withoutCountryCode($address->phone_number);
            if ($addressPhone !== '' && $addressPhone !== $primaryNumber) {
                return $addressPhone;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildAddresses(Customer $customer): array
    {
        if ($customer->addresses->isEmpty()) {
            return [[
                'address'    => '',
                'is_Default' => true,
            ]];
        }

        return $customer->addresses
            ->values()
            ->map(function (CustomerAddress $address, int $index) {
                return [
                    'address'    => $this->formatAddress($address),
                    'is_Default' => $index === 0,
                ];
            })
            ->all();
    }

    private function formatAddress(CustomerAddress $address): string
    {
        $address->loadMissing('governorate', 'area');

        $parts = [];

        if ($address->governorate?->name_en) {
            $parts[] = $address->governorate->name_en;
        }
        if ($address->area?->name_en) {
            $parts[] = $address->area->name_en;
        }
        if (!empty($address->type)) {
            $parts[] = (string) $address->type;
        }
        if (!empty($address->building_name)) {
            $parts[] = (string) $address->building_name;
        }
        if (!empty($address->apartment_number)) {
            $parts[] = 'Apt ' . $address->apartment_number;
        }
        if (!empty($address->company)) {
            $parts[] = (string) $address->company;
        }
        if (!empty($address->street)) {
            $parts[] = (string) $address->street;
        }
        if (!empty($address->house)) {
            $parts[] = (string) $address->house;
        }
        if (!empty($address->block)) {
            $parts[] = 'Block ' . $address->block;
        }
        if (!empty($address->floor)) {
            $parts[] = 'Floor ' . $address->floor;
        }
        if (!empty($address->additional_directions)) {
            $parts[] = (string) $address->additional_directions;
        }

        return implode(' | ', array_filter($parts));
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
}
