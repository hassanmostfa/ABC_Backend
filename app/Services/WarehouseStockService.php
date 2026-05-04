<?php

namespace App\Services;

use GuzzleHttp\TransferStats;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WarehouseStockService
{
    private string $baseUrl;
    private string $endpoint;
    private string $defaultCode;
    private string $username;
    private string $password;
    private int $timeout;
    private int $connectTimeout;
    private int $retries;
    private int $retrySleepMs;

    public function __construct()
    {
        $this->baseUrl  = rtrim(config('services.warehouse_stock.url', ''), '/');
        $this->endpoint = config('services.warehouse_stock.endpoint', '/API/order/GetWHStock');
        $this->defaultCode = config('services.warehouse_stock.default_code', 'FGW1');
        $this->username = config('services.warehouse_stock.username', '');
        $this->password = config('services.warehouse_stock.password', '');
        $this->timeout  = (int) config('services.warehouse_stock.timeout', 30);
        $this->connectTimeout = (int) config('services.warehouse_stock.connect_timeout', 10);
        $this->retries = (int) config('services.warehouse_stock.retries', 2);
        $this->retrySleepMs = (int) config('services.warehouse_stock.retry_sleep_ms', 1000);
    }

    /**
     * Get warehouse stock for a given warehouse code.
     *
     * @param  string  $warehouseCode  The warehouse code (e.g., "FGW1")
     * @return array{success: bool, status: int|null, body: mixed, error: string|null}
     */
    public function getStock(string $warehouseCode): array
    {
        $query = ['WHCode' => $warehouseCode];

        return $this->request($query, [
            'log_context' => [
                'action' => 'GetWHStock',
                'warehouse_code' => $warehouseCode,
            ],
        ]);
    }

    /**
     * @param  array<string, scalar|array|null>  $query  Query string parameters
     * @param  array{log_context?: array}  $options
     * @return array{success: bool, status: int|null, body: mixed, error: string|null}
     */
    private function request(array $query, array $options = []): array
    {
        $url = $this->buildUrl();
        $logContext = array_merge(['method' => 'GET', 'url' => $url], $options['log_context'] ?? []);

        $maxAttempts = max(1, $this->retries + 1);

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $requestStats = [];

            try {
                $pending = Http::withBasicAuth($this->username, $this->password)
                    ->connectTimeout($this->connectTimeout)
                    ->timeout($this->timeout)
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'Connection' => 'close',
                        'Expect' => '',
                    ])
                    ->withOptions([
                        'verify' => false,
                        'version' => 1.1,
                        'curl' => [
                            CURLOPT_FORBID_REUSE => true,
                            CURLOPT_FRESH_CONNECT => true,
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_SSL_VERIFYHOST => false,
                            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
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
                    ]);

                $response = $pending->get($url, $query);

                $success = $response->successful();
                
                $bodyData = $response->json();
                if ($bodyData === null && !empty($response->body())) {
                    $bodyData = $response->body();
                }

                Log::channel('erp')->info('Warehouse GetWHStock', array_merge($logContext, [
                    'http_status'   => $response->status(),
                    'success'       => $success,
                    'attempt'       => $attempt,
                    'max_attempts'  => $maxAttempts,
                    'query'         => $query,
                    'stats'         => $requestStats,
                    'response'      => $bodyData,
                    'response_raw'  => $response->body(),
                ]));

                return [
                    'success' => $success,
                    'status'  => $response->status(),
                    'body'    => $bodyData,
                    'error'   => $success ? null : 'Warehouse API responded with HTTP ' . $response->status(),
                ];
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $isLastAttempt = $attempt >= $maxAttempts;

                Log::channel('erp')->{$isLastAttempt ? 'error' : 'warning'}(
                    $isLastAttempt ? 'Warehouse connection error' : 'Warehouse connection error, retrying',
                    array_merge($logContext, [
                        'attempt'      => $attempt,
                        'max_attempts' => $maxAttempts,
                        'stats'        => $requestStats,
                        'message'      => $e->getMessage(),
                    ])
                );

                if ($isLastAttempt) {
                    return [
                        'success' => false,
                        'status'  => null,
                        'body'    => null,
                        'error'   => 'Warehouse connection error: ' . $e->getMessage(),
                    ];
                }

                if ($this->retrySleepMs > 0) {
                    usleep($this->retrySleepMs * 1000);
                }
            } catch (\Throwable $e) {
                Log::channel('erp')->error('Warehouse request error', array_merge($logContext, [
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
            'error'   => 'Warehouse request failed unexpectedly before execution.',
        ];
    }

    private function buildUrl(): string
    {
        return $this->baseUrl . $this->endpoint;
    }
}
