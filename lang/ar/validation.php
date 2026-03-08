<?php

return [
    'required' => 'حقل :attribute مطلوب.',
    'confirmed' => 'تأكيد :attribute غير متطابق.',
    'string' => 'يجب أن يكون :attribute نصاً.',
    'max' => [
        'string' => 'لا يجوز أن يتجاوز :attribute :max حرفاً.',
        'numeric' => 'لا يجوز أن يتجاوز :attribute :max.',
        'array' => 'لا يجوز أن يحتوي :attribute على أكثر من :max عناصر.',
    ],
    'min' => [
        'string' => 'يجب أن يكون :attribute على الأقل :min حرفاً.',
        'numeric' => 'يجب أن يكون :attribute على الأقل :min.',
        'array' => 'يجب أن يحتوي :attribute على الأقل :min عناصر.',
    ],
    'numeric' => 'يجب أن يكون :attribute رقماً.',
    'integer' => 'يجب أن يكون :attribute عدداً صحيحاً.',
    'in' => 'قيمة :attribute غير صالحة.',
    'size' => [
        'string' => 'يجب أن يكون :attribute :size أحرفاً.',
    ],
    'uuid' => 'يجب أن يكون :attribute UUID صالحاً.',
    'exists' => 'قيمة :attribute المحددة غير صالحة.',
    'required_with' => 'حقل :attribute مطلوب عند وجود :values.',
    'attributes' => [
        'phone' => 'رقم الهاتف',
        'phone_code' => 'رمز الهاتف',
        'otp_type' => 'نوع التحقق',
        'verification_token' => 'رمز التحقق',
        'otp_code' => 'رمز OTP',
        'device_token' => 'رمز الجهاز',
        'code' => 'الكود',
        'order_amount' => 'مبلغ الطلب',
        'variant_ids' => 'المتغيرات',
        'amount' => 'المبلغ',
        'customer_address_id' => 'العنوان',
        'payment_method' => 'طريقة الدفع',
    ],
];
