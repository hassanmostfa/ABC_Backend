<?php

return [
    'base_url' => env('SMSBOX_BASE_URL', 'http://smsbox.com/SMSGateway/Services/Messaging.asmx/Http_SendSMS'),
    'username' => env('SMSBOX_USERNAME'),
    'password' => env('SMSBOX_PASSWORD'),
    'customer_id' => env('SMSBOX_CUSTOMER_ID'),
    'sender' => env('SMSBOX_SENDER'),
];
