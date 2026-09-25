<?php

namespace App\Services\Notification;

use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    protected array $credentials;

    protected string $projectId;

    protected Client $client;

    public function __construct()
    {
        $credentialsPath = base_path(config('firebase.credentials'));

        if (! is_file($credentialsPath)) {
            throw new \RuntimeException('Firebase credentials file not found: ' . $credentialsPath);
        }

        $this->credentials = json_decode((string) file_get_contents($credentialsPath), true, 512, JSON_THROW_ON_ERROR);
        $this->projectId = $this->credentials['project_id'];

        $this->client = new Client([
            'base_uri' => "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send",
            ...$this->timeoutOptions(),
        ]);
    }

    /**
     * @return array<string, float>
     */
    protected function timeoutOptions(): array
    {
        return [
            'connect_timeout' => (float) config('firebase.connect_timeout', 5),
            'timeout' => (float) config('firebase.timeout', 10),
        ];
    }

    protected function getAccessToken(): string
    {
        return Cache::remember('firebase_access_token', 3500, function () {
            $jwt = $this->createJwt();
            $response = (new Client($this->timeoutOptions()))->post('https://oauth2.googleapis.com/token', [
                'form_params' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ],
            ]);

            $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

            return $body['access_token'];
        });
    }

    protected function createJwt(): string
    {
        $now = time();
        $payload = [
            'iss' => $this->credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        return JWT::encode($payload, $this->credentials['private_key'], 'RS256');
    }

    /**
     * Validate credentials and OAuth token (for testing). Throws on failure.
     */
    public function testConnection(): void
    {
        $this->getAccessToken();
    }

    /**
     * Send FCM notification to multiple device tokens.
     *
     * @param  array<string>  $tokens  FCM device tokens
     * @param  array<string, string>  $data  Optional data payload (string values only for FCM)
     * @return array<string, array<string, mixed>>
     */
    public function sendNotificationToMultiple(array $tokens, string $title, string $body, array $data = []): array
    {
        $results = [];
        $accessToken = $this->getAccessToken();

        foreach ($tokens as $token) {
            $message = $this->buildMessage($token, $title, $body, $data);

            try {
                $response = $this->client->post('', [
                    'headers' => [
                        'Authorization' => "Bearer {$accessToken}",
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $message,
                ]);

                $results[$token] = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            } catch (GuzzleException $e) {
                Log::error('Firebase Notification Failed', [
                    'token' => $token,
                    'title' => $title,
                    'body' => $body,
                    'data' => $data,
                    'error' => $e->getMessage(),
                ]);

                $results[$token] = [
                    'error' => true,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Send many individual messages in parallel.
     *
     * @param  array<int|string, array{token: string, title: string, body: string, data?: array<string, mixed>}>  $messages
     * @return array<int|string, array{success: bool, invalid_token: bool, error: string|null}> keyed like $messages
     */
    public function sendMessagesConcurrently(array $messages, int $concurrency = 50): array
    {
        if (empty($messages)) {
            return [];
        }

        $accessToken = $this->getAccessToken();
        $results = [];

        $requests = function () use ($messages, $accessToken) {
            foreach ($messages as $key => $message) {
                yield $key => fn () => $this->client->postAsync('', [
                    'headers' => [
                        'Authorization' => "Bearer {$accessToken}",
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $this->buildMessage($message['token'], $message['title'], $message['body'], $message['data'] ?? []),
                ]);
            }
        };

        $pool = new Pool($this->client, $requests(), [
            'concurrency' => max(1, $concurrency),
            'fulfilled' => function ($response, $key) use (&$results) {
                $results[$key] = ['success' => true, 'invalid_token' => false, 'error' => null];
            },
            'rejected' => function ($reason, $key) use (&$results, $messages) {
                $response = $reason instanceof RequestException ? $reason->getResponse() : null;
                $error = $response ? (string) $response->getBody() : $reason->getMessage();

                $results[$key] = [
                    'success' => false,
                    'invalid_token' => $response !== null && $this->isInvalidTokenError($response->getStatusCode(), $error),
                    'error' => $error,
                ];

                Log::warning('Firebase Notification Failed', [
                    'token' => $messages[$key]['token'],
                    'error' => $error,
                ]);
            },
        ]);

        $pool->promise()->wait();

        return $results;
    }

    /**
     * True when FCM says the token itself is dead (app uninstalled, token rotated or malformed).
     */
    protected function isInvalidTokenError(int $statusCode, string $body): bool
    {
        $error = json_decode($body, true)['error'] ?? [];
        $errorCodes = collect($error['details'] ?? [])->pluck('errorCode')->filter()->all();

        if ($statusCode === 404 || in_array('UNREGISTERED', $errorCodes, true)) {
            return true;
        }

        return $statusCode === 400
            && in_array('INVALID_ARGUMENT', $errorCodes, true)
            && str_contains(strtolower((string) ($error['message'] ?? '')), 'registration token');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function buildMessage(string $token, string $title, string $body, array $data = []): array
    {
        return [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                // FCM data payload must have string values
                'data' => (object) array_map(fn ($v) => (string) $v, $data),
                'android' => [
                    'notification' => [
                        'sound' => 'default',
                    ],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                        ],
                    ],
                ],
            ],
        ];
    }
}
