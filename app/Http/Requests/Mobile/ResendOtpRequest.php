<?php

namespace App\Http\Requests\Mobile;

class ResendOtpRequest extends MobileFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:20'],
            'phone_code' => ['nullable', 'string', 'max:10'],
            'otp_type' => ['nullable', 'string', 'in:login,register'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => $this->msg('The phone field is required.', 'رقم الهاتف مطلوب.'),
            'phone.string' => $this->msg('The phone must be a string.', 'يجب أن يكون رقم الهاتف نصاً.'),
            'phone.max' => $this->msg('The phone may not be greater than 20 characters.', 'رقم الهاتف لا يجوز أن يتجاوز 20 حرفاً.'),
            'phone_code.string' => $this->msg('The phone code must be a string.', 'يجب أن يكون رمز الهاتف نصاً.'),
            'phone_code.max' => $this->msg('The phone code may not be greater than 10 characters.', 'رمز الهاتف لا يجوز أن يتجاوز 10 أحرف.'),
            'otp_type.in' => $this->msg('The otp type must be either login or register.', 'نوع التحقق يجب أن يكون تسجيل دخول أو تسجيل جديد.'),
        ];
    }
}

