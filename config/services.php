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
    'upayments' => [
        'key' => env('UPAYMENTS_API_KEY'),
        'url' => env('UPAYMENTS_API_URL'),
        'status_endpoint' => env('UPAYMENTS_STATUS_ENDPOINT', '/api/v1/getpaymentstatus'),
        /** "create-invoice" = invoice link (dev-uinvoice/uinvoice). Empty = default charge = session redirect (sandbox.upayments.com?session_id=...). */
        'payment_gateway_src' => env('UPAYMENTS_PAYMENT_GATEWAY_SRC', 'create-invoice'),
        /** Required when payment_gateway_src is create-invoice: "link" (return URL), "email", "sms", or "all". */
        'notification_type' => env('UPAYMENTS_NOTIFICATION_TYPE', 'link'),
        /** When false (e.g. local), success callback updates payment/invoice from redirect params only. Set true in production. */
        'verify_via_status_api' => env('UPAYMENTS_VERIFY_VIA_STATUS_API', true),
        'logging_channel' => env('UPAYMENTS_LOGGING_CHANNEL'),
        'logging_enabled' => env('UPAYMENTS_LOGGING_ENABLED'),
    ],


];
