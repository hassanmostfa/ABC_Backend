<?php

use App\Models\DeviceToken;
use App\Services\FirebaseService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('firebase:test {--token= : FCM device token to send test notification to} {--customer= : Customer ID to send test notification to (uses their device tokens)}', function () {
    $this->info('Testing Firebase (FCM) setup...');

    try {
        $firebase = app(FirebaseService::class);
        $this->info('Credentials file: OK');
    } catch (\Throwable $e) {
        $this->error('Credentials failed: ' . $e->getMessage());
        return 1;
    }

    try {
        $firebase->testConnection();
        $this->info('OAuth token: OK');
    } catch (\Throwable $e) {
        $this->error('OAuth token failed: ' . $e->getMessage());
        return 1;
    }

    $sendToken = $this->option('token');
    $customerId = $this->option('customer');

    if ($sendToken) {
        $tokens = [ $sendToken ];
        $this->info('Sending test notification to 1 token...');
    } elseif ($customerId) {
        $tokens = DeviceToken::where('customer_id', (int) $customerId)->pluck('token')->filter()->values()->all();
        if (empty($tokens)) {
            $this->warn('Customer ' . $customerId . ' has no device tokens. Register a device (e.g. login from app) first.');
            return 0;
        }
        $this->info('Sending test notification to ' . count($tokens) . ' device(s) for customer ' . $customerId . '...');
    } else {
        $this->newLine();
        $this->info('Firebase is working. To send a test push:');
        $this->line('  php artisan firebase:test --token="YOUR_FCM_DEVICE_TOKEN"');
        $this->line('  php artisan firebase:test --customer=1');
        return 0;
    }

    $title = 'Welcome to ABC App';
    $body = 'Welcome to ABC App. This is a test notification.';
    $data = [ 'type' => 'test', 'sent_at' => now()->toIso8601String() ];

    $results = $firebase->sendNotificationToMultiple($tokens, $title, $body, $data);

    $ok = 0;
    $fail = 0;
    foreach ($results as $t => $res) {
        if (isset($res['error']) && $res['error']) {
            $this->error('  Token ' . substr($t, 0, 20) . '...: ' . ($res['message'] ?? 'Unknown error'));
            $fail++;
        } else {
            $this->info('  Token ' . substr($t, 0, 20) . '...: Sent');
            $ok++;
        }
    }

    $this->newLine();
    $this->info('Done. Sent: ' . $ok . ', Failed: ' . $fail);
    return $fail > 0 ? 1 : 0;
})->purpose('Test Firebase credentials and optionally send a test push notification');
