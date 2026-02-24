<?php

return [
    'base_url' => env('SMSBOX_BASE_URL', 'http://smsbox.com/SMSGateway/Services/Messaging.asmx/Http_SendSMS'),
    'username' => env('SMSBOX_USERNAME'),
    'password' => env('SMSBOX_PASSWORD'),
    'customer_id' => env('SMSBOX_CUSTOMER_ID'),
    'sender' => env('SMSBOX_SENDER'),
    // Some gateways return 403 if User-Agent looks like a script; set to a browser-like string if needed
    'user_agent' => env('SMSBOX_USER_AGENT', 'Mozilla/5.0 (compatible; SmsBoxClient/1.0)'),
];
