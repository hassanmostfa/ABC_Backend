<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsBoxService
{
    /**
     * Send SMS via SMSBOX HTTP API.
     *
     * @return array{success: bool, message_id: string|null, net_points: float|null, rejected_numbers: array}
     */
    public function send(string $phone, string $message): array
    {
        $baseUrl = config('smsbox.base_url');
        if (empty($baseUrl)) {
            Log::warning('SmsBoxService: base_url not configured');
            return [
                'success' => false,
                'message_id' => null,
                'net_points' => null,
                'rejected_numbers' => [],
            ];
        }

        $payload = [
            'username' => config('smsbox.username'),
            'password' => config('smsbox.password'),
            'customerId' => config('smsbox.customer_id'),
            'senderText' => config('smsbox.sender'),
            'messageBody' => $message,
            'recipientNumbers' => $phone,
            'defdate' => '',
            'isBlink' => 'false',
            'isFlash' => 'false',
        ];

        try {
            $response = Http::timeout(15)
                ->withoutVerifying()
                ->withHeaders([
                    'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Connection'      => 'keep-alive',
                ])
                ->get($baseUrl, $payload);

            $body = $response->body();
            $normalized = $this->parseResponse($body, $response->successful());

            if (!$response->successful()) {
                Log::warning('SmsBoxService: HTTP error', [
                    'status' => $response->status(),
                    'url' => $baseUrl,
                    'body_preview' => strlen($body) > 200 ? substr($body, 0, 200) . '...' : $body,
                ]);
            } else {
                Log::info('SmsBoxService: SMS sent successfully', [
                    'status' => $response->status(),
                ]);
            }

            return $normalized;
        } catch (\Throwable $e) {
            Log::warning('SmsBoxService: request failed', [
                'message' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message_id' => null,
                'net_points' => null,
                'rejected_numbers' => [],
            ];
        }
    }

    /**
     * Parse XML response from SMSBOX API.
     *
     * @return array{success: bool, message_id: string|null, net_points: float|null, rejected_numbers: array}
     */
    protected function parseResponse(string $body, bool $httpOk): array
    {
        $default = [
            'success' => false,
            'message_id' => null,
            'net_points' => null,
            'rejected_numbers' => [],
        ];

        if (trim($body) === '') {
            return array_merge($default, ['success' => $httpOk]);
        }

        $previous = libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($body);
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            return $default;
        }

        $messageId = null;
        $netPoints = null;
        $rejectedNumbers = [];
        $success = $httpOk;

        // Try common node names (adapt if API docs specify exact structure)
        if (isset($xml->MessageID)) {
            $messageId = (string) $xml->MessageID;
        }
        if (isset($xml->NetPoints)) {
            $netPoints = (float) $xml->NetPoints;
        }
        if (isset($xml->RejectedNumbers) && is_iterable($xml->RejectedNumbers)) {
            foreach ($xml->RejectedNumbers as $node) {
                $rejectedNumbers[] = (string) $node;
            }
        }
        // Single value or string list
        if (isset($xml->RejectedNumbers) && is_string((string) $xml->RejectedNumbers)) {
            $str = trim((string) $xml->RejectedNumbers);
            if ($str !== '') {
                $rejectedNumbers = array_filter(array_map('trim', explode(',', $str)));
            }
        }
        if (isset($xml->Result) && strtolower((string) $xml->Result) === 'ok') {
            $success = true;
        }
        if (isset($xml->Error) && (string) $xml->Error !== '') {
            $success = false;
        }

        return [
            'success' => $success,
            'message_id' => $messageId,
            'net_points' => $netPoints,
            'rejected_numbers' => $rejectedNumbers,
        ];
    }
}
