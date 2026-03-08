<?php

return [
    'required' => 'The :attribute field is required.',
    'confirmed' => 'The :attribute confirmation does not match.',
    'string' => 'The :attribute must be a string.',
    'max' => [
        'string' => 'The :attribute may not be greater than :max characters.',
        'numeric' => 'The :attribute may not be greater than :max.',
        'array' => 'The :attribute may not have more than :max items.',
    ],
    'min' => [
        'string' => 'The :attribute must be at least :min characters.',
        'numeric' => 'The :attribute must be at least :min.',
        'array' => 'The :attribute must have at least :min items.',
    ],
    'numeric' => 'The :attribute must be a number.',
    'integer' => 'The :attribute must be an integer.',
    'in' => 'The selected :attribute is invalid.',
    'size' => [
        'string' => 'The :attribute must be :size characters.',
    ],
    'uuid' => 'The :attribute must be a valid UUID.',
    'exists' => 'The selected :attribute is invalid.',
    'required_with' => 'The :attribute field is required when :values is present.',
    'attributes' => [
        'phone' => 'phone',
        'phone_code' => 'phone code',
        'otp_type' => 'otp type',
        'verification_token' => 'verification token',
        'otp_code' => 'OTP code',
        'device_token' => 'device token',
        'code' => 'code',
        'order_amount' => 'order amount',
        'variant_ids' => 'variant ids',
        'amount' => 'amount',
        'customer_address_id' => 'customer address',
        'payment_method' => 'payment method',
    ],
];
