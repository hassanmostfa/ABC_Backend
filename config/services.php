<?php

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
        'url'             => env('ERP_URL', 'http://31.214.1.139:61402'),
        'username'        => env('ERP_USERNAME', 'SONIC'),
        'password'        => env('ERP_PASSWORD', 'S0n!c@AP!'),
        'timeout'         => (int) env('ERP_TIMEOUT', 30),
        'connect_timeout' => (int) env('ERP_CONNECT_TIMEOUT', 10),
        'retries'         => (int) env('ERP_RETRIES', 2),
        'retry_sleep_ms'  => (int) env('ERP_RETRY_SLEEP_MS', 1000),
        'log_failed_payload' => env('ERP_LOG_FAILED_PAYLOAD', false),
    ],

    /*
    | Octopus integration: POST /api/octopus/orders
    | Send Authorization: Bearer {OCTOPUS_API_TOKEN} or X-Access-Token: {token}
    | Token must start with abc_
    */
    'octopus' => [
        'access_token' => env('OCTOPUS_API_TOKEN'),
    ],

    'upayments' => [
        'key' => env('UPAYMENTS_API_KEY'),
        'url' => env('UPAYMENTS_API_URL'),
        'status_endpoint' => env('UPAYMENTS_STATUS_ENDPOINT', '/api/v1/getpaymentstatus'),
        /** Fallback when no per-request src is passed. Empty = no paymentGateway in payload. "create-invoice" = invoice link + notificationType. Orders/wallet use request body src (knet|cc). */
        'payment_gateway_src' => env('UPAYMENTS_PAYMENT_GATEWAY_SRC', ''),
        /** Required when payment_gateway_src is create-invoice: "link" (return URL), "email", "sms", or "all". */
        'notification_type' => env('UPAYMENTS_NOTIFICATION_TYPE', 'link'),
        /** When false (e.g. local), success callback updates payment/invoice from redirect params only. Set true in production. */
        'verify_via_status_api' => env('UPAYMENTS_VERIFY_VIA_STATUS_API', true),
        'logging_channel' => env('UPAYMENTS_LOGGING_CHANNEL'),
        'logging_enabled' => env('UPAYMENTS_LOGGING_ENABLED'),
        /** Request timeout in seconds (default 60). Increase if sandbox is slow; if timeouts persist, check network/firewall. */
        'timeout' => (int) env('UPAYMENTS_TIMEOUT', 60),
        /** Connection timeout in seconds (default 15). */
        'connect_timeout' => (int) env('UPAYMENTS_CONNECT_TIMEOUT', 15),
        /** Browser redirects after Upayments for orders with number prefix WEB- (website checkout). */
        'website_return_url' => env('UPAYMENTS_WEBSITE_RETURN_URL', 'https://abc-website-enhanced-wiys.vercel.app/en/payment/success'),
        'website_cancel_url' => env('UPAYMENTS_WEBSITE_CANCEL_URL', 'https://abc-website-enhanced-wiys.vercel.app/en/payment/failed'),
    ],


];
