<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Admin Notifications
    |--------------------------------------------------------------------------
    |
    | Keep this false for now to disable creating admin notifications.
    | Set to true later when you want to activate admin notifications.
    |
    */
    'send_to_admins' => env('NOTIFICATIONS_SEND_TO_ADMINS', false),

    /*
    |--------------------------------------------------------------------------
    | Firebase (FCM) Push Notifications
    |--------------------------------------------------------------------------
    |
    | When true, customer notifications are also sent as push via Firebase.
    | Requires FIREBASE_CREDENTIALS_PATH and customer device tokens.
    |
    */
    'send_to_firebase' => env('NOTIFICATIONS_SEND_TO_FIREBASE', true),

    /*
    |--------------------------------------------------------------------------
    | General (Broadcast) Notifications
    |--------------------------------------------------------------------------
    |
    | Broadcasts to all customers run on their own queue so they never delay
    | order / ERP jobs. The worker must listen on it, e.g.:
    |   php artisan queue:work --queue=default,notifications
    |
    | push_concurrency = parallel FCM requests per chunk job.
    |
    */
    'queue' => env('NOTIFICATIONS_QUEUE', 'notifications'),

    'push_concurrency' => (int) env('NOTIFICATIONS_PUSH_CONCURRENCY', 50),
];
