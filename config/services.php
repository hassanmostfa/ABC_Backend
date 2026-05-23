<?php

$payments = require __DIR__ . '/payments.php';

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'erp' => [
        'url'             => env('ERP_URL'),
        'username'        => env('ERP_USERNAME'),
        'password'        => env('ERP_PASSWORD'),
        'timeout'         => (int) env('ERP_TIMEOUT', 30),
        'connect_timeout' => (int) env('ERP_CONNECT_TIMEOUT', 10),
        'retries'         => (int) env('ERP_RETRIES', 2),
        'retry_sleep_ms'  => (int) env('ERP_RETRY_SLEEP_MS', 1000),
        'log_failed_payload' => env('ERP_LOG_FAILED_PAYLOAD', false),
    ],

    'warehouse_stock' => [
        'url'             => env('WAREHOUSE_STOCK_URL'),
        'username'        => env('WAREHOUSE_STOCK_USERNAME'),
        'password'        => env('WAREHOUSE_STOCK_PASSWORD'),
        'endpoint'        => env('WAREHOUSE_STOCK_ENDPOINT', '/API/order/GetWHStock'),
        'default_code'    => env('WAREHOUSE_STOCK_DEFAULT_CODE', 'FGW1'),
        'driver'          => env('WAREHOUSE_STOCK_DRIVER', 'stream'),
        'curl_path'       => env('WAREHOUSE_STOCK_CURL_PATH', 'curl'),
        'timeout'         => (int) env('WAREHOUSE_STOCK_TIMEOUT', 30),
        'connect_timeout' => (int) env('WAREHOUSE_STOCK_CONNECT_TIMEOUT', 10),
        'retries'         => (int) env('WAREHOUSE_STOCK_RETRIES', 2),
        'retry_sleep_ms'  => (int) env('WAREHOUSE_STOCK_RETRY_SLEEP_MS', 1000),
    ],

    /*
    | Octopus integration: POST /api/octopus/orders
    | Send Authorization: Bearer {OCTOPUS_API_TOKEN} or X-Access-Token: {token}
    | Token must start with abc_
    */
    'octopus' => [
        'access_token' => env('OCTOPUS_API_TOKEN'),
    ],

    'upayments' => $payments['upayments'],

    'ottu' => $payments['ottu'],


];
