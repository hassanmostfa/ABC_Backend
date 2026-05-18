<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS)
|--------------------------------------------------------------------------
|
| Restrict origins via CORS_ALLOWED_ORIGINS in .env (comma-separated URLs).
| Never use "*" in production — it allows any site to call your API from a browser.
|
| Example:
| CORS_ALLOWED_ORIGINS=http://localhost:3000,https://abc-website-enhanced-wiys.vercel.app
|
*/

$allowedOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://127.0.0.1:3000'))
)));

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'Origin', 'LANG', 'X-Access-Token', 'X-CSRF-TOKEN','Accept-Language'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
