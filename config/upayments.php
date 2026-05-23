<?php

$payments = require __DIR__ . '/payments.php';

return [
    'api_key' => $payments['upayments']['key'],
    'api_base_url' => $payments['upayments']['url'],
    'logging_channel' => $payments['upayments']['logging_channel'] ?? env('UPAYMENTS_LOGGING_CHANNEL', 'stack'),
    'logging_enabled' => $payments['upayments']['logging_enabled'] ?? env('UPAYMENTS_LOGGING_ENABLED', true),
];
