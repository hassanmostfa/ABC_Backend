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
];
