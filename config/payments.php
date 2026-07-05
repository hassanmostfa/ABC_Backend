<?php

$mode = strtolower(trim((string) env('PAYMENTS_MODE', 'test')));
$isLive = in_array($mode, ['live', 'production'], true);
$mode = $isLive ? 'live' : 'test';

/**
 * Resolve mode-specific payment env vars with fallback to legacy unprefixed keys.
 *
 * Examples:
 * - OTTU_TEST_API_KEY / OTTU_LIVE_API_KEY -> fallback OTTU_API_KEY
 * - UPAYMENTS_TEST_API_URL / UPAYMENTS_LIVE_API_URL -> fallback UPAYMENTS_API_URL
 */
$paymentEnv = static function (string $prefix, string $key, $default = null) use ($isLive) {
    $modeSegment = $isLive ? 'LIVE' : 'TEST';
    $modeSpecific = env("{$prefix}_{$modeSegment}_{$key}");
    if ($modeSpecific !== null && $modeSpecific !== '') {
        return $modeSpecific;
    }

    $legacy = env("{$prefix}_{$key}", $default);

    return $legacy;
};

return [
    'mode' => $mode,

    'ottu' => [
        'mode' => $mode,
        'api_key' => $paymentEnv('OTTU', 'API_KEY'),
        'url' => rtrim((string) (
            $paymentEnv('OTTU', 'URL')
            ?: ($isLive ? 'https://pay.ottu.net' : 'https://sandbox.ottu.net')
        ), '/'),
        'hmac_key' => $paymentEnv('OTTU', 'HMAC_KEY'),
        'skip_signature_verify' => (bool) $paymentEnv('OTTU', 'SKIP_SIGNATURE_VERIFY', false),
        'pg_code' => $paymentEnv('OTTU', 'PG_CODE', 'credit-card'),
        'type' => env('OTTU_TYPE', 'payment_request'),
        'currency' => env('OTTU_CURRENCY', 'KWD'),
        'timeout' => (int) env('OTTU_TIMEOUT', 60),
        'connect_timeout' => (int) env('OTTU_CONNECT_TIMEOUT', 15),
        'website_return_url' => env('OTTU_WEBSITE_RETURN_URL', 'https://abc-website-enhanced-wiys.vercel.app/en/payment/success'),
        'website_cancel_url' => env('OTTU_WEBSITE_CANCEL_URL', 'https://abc-website-enhanced-wiys.vercel.app/en/payment/failed'),
        'checkout_ttl_minutes' => (int) env('OTTU_CHECKOUT_TTL_MINUTES', 60),
        'enable_pending_status' => (bool) env('OTTU_ENABLE_PENDING_STATUS', true),
    ],

    'upayments' => [
        'mode' => $mode,
        'key' => $paymentEnv('UPAYMENTS', 'API_KEY'),
        'url' => $paymentEnv('UPAYMENTS', 'API_URL') ?: (
            $isLive
                ? 'https://apiv2api.upayments.com/api/v1'
                : 'https://sandboxapi.upayments.com/api/v1'
        ),
        'status_endpoint' => env('UPAYMENTS_STATUS_ENDPOINT', '/api/v1/getpaymentstatus'),
        'payment_gateway_src' => $paymentEnv('UPAYMENTS', 'PAYMENT_GATEWAY_SRC', ''),
        'notification_type' => env('UPAYMENTS_NOTIFICATION_TYPE', 'link'),
        'verify_via_status_api' => env('UPAYMENTS_VERIFY_VIA_STATUS_API', true),
        'logging_channel' => env('UPAYMENTS_LOGGING_CHANNEL'),
        'logging_enabled' => env('UPAYMENTS_LOGGING_ENABLED'),
        'timeout' => (int) env('UPAYMENTS_TIMEOUT', 60),
        'connect_timeout' => (int) env('UPAYMENTS_CONNECT_TIMEOUT', 15),
        'website_return_url' => env('UPAYMENTS_WEBSITE_RETURN_URL', 'https://abc-website-enhanced-wiys.vercel.app/en/payment/success'),
        'website_cancel_url' => env('UPAYMENTS_WEBSITE_CANCEL_URL', 'https://abc-website-enhanced-wiys.vercel.app/en/payment/failed'),
    ],
];
