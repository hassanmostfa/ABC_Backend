<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SmsBoxService
{
    /**
     * Send SMS via SMSBOX HTTP API using native cURL.
     *
     * @return array{success: bool, message_id: string|null, net_points: float|null, rejected_numbers: array}
     */
    public function send(string $phone, string $message): array
    {
        $baseUrl = config('smsbox.base_url');
        if (empty($baseUrl)) {
            Log::warning('SmsBoxService: base_url not configured');
            return $this->fail();
        }

        $query = http_build_query([
            'username'         => config('smsbox.username'),
            'password'         => config('smsbox.password'),
            'customerId'       => config('smsbox.customer_id'),
            'senderText'       => config('smsbox.sender'),
            'messageBody'      => $message,
            'recipientNumbers' => $phone,
            'defdate'          => '',
            'isBlink'          => 'false',
            'isFlash'          => 'false',
        ]);

        $url = $baseUrl . '?' . $query;

        $result = $this->curlGet($url);

        // If direct HTTPS fails with 403, try resolving the origin IP to bypass CDN blocking
        if ($result['http_code'] === 403) {
            Log::info('SmsBoxService: Direct request returned 403, trying via resolved IP');
            $host = parse_url($baseUrl, PHP_URL_HOST);
            $ip = gethostbyname($host);
            if ($ip && $ip !== $host) {
                $ipUrl = str_replace($host, $ip, $url);
                $result = $this->curlGet($ipUrl, $host);
                Log::info('SmsBoxService: IP-based attempt', [
                    'ip' => $ip, 'http_code' => $result['http_code'],
                ]);
            }
        }

        $body = $result['body'];
        $httpCode = $result['http_code'];
        $success = $httpCode >= 200 && $httpCode < 300;

        if ($result['error']) {
            Log::warning('SmsBoxService: cURL error', [
                'error' => $result['error'],
                'url'   => $url,
            ]);
            return $this->fail();
        }

        if (!$success) {
            Log::warning('SmsBoxService: HTTP error', [
                'status'       => $httpCode,
                'url'          => $url,
                'body_preview' => mb_substr($body, 0, 300),
            ]);
        } else {
            Log::info('SmsBoxService: SMS sent successfully', ['status' => $httpCode]);
        }

        return $this->parseResponse($body, $success);
    }

    /**
     * Perform a GET request using native PHP cURL.
     * When $hostHeader is provided, it is sent as the Host header (for IP-based requests).
     */
    protected function curlGet(string $url, ?string $hostHeader = null): array
    {
        $ch = curl_init();

        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Accept-Encoding: gzip, deflate',
            'Connection: keep-alive',
            'Referer: https://smsbox.com/',
        ];

        if ($hostHeader) {
            $headers[] = 'Host: ' . $hostHeader;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_ENCODING       => '',
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => $headers,
        ]);

        $body     = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        $ip       = curl_getinfo($ch, CURLINFO_PRIMARY_IP);

        curl_close($ch);

        if ($httpCode === 403 || $error) {
            Log::debug('SmsBoxService: cURL debug', [
                'resolved_ip' => $ip,
                'http_code'   => $httpCode,
                'error'       => $error ?: 'none',
            ]);
        }

        return [
            'body'      => $body ?: '',
            'http_code' => $httpCode,
            'error'     => $error ?: null,
        ];
    }

    private function fail(): array
    {
        return [
            'success'          => false,
            'message_id'       => null,
            'net_points'       => null,
            'rejected_numbers' => [],
        ];
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
