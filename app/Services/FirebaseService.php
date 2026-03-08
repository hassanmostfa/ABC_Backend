<?php

namespace App\Services;

use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
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
        ]);
    }

    protected function getAccessToken(): string
    {
        return Cache::remember('firebase_access_token', 3500, function () {
            $jwt = $this->createJwt();
            $response = (new Client())->post('https://oauth2.googleapis.com/token', [
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

        // FCM data payload must have string values
        $data = array_map(fn ($v) => (string) $v, $data);

        foreach ($tokens as $token) {
            $message = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $data,
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
}
