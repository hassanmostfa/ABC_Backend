<?php

namespace App\Http\Requests\Mobile;

class VerifyOtpRequest extends MobileFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'verification_token' => ['required', 'string', 'uuid'],
            'otp_code' => ['required', 'string', 'size:4'],
            'device_token' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'verification_token.required' => $this->msg('The verification token is required.', 'رمز التحقق مطلوب.'),
            'verification_token.uuid' => $this->msg('The verification token must be a valid UUID.', 'رمز التحقق يجب أن يكون UUID صالحاً.'),
            'otp_code.required' => $this->msg('The OTP code is required.', 'رمز OTP مطلوب.'),
            'otp_code.string' => $this->msg('The OTP code must be a string.', 'يجب أن يكون رمز OTP نصاً.'),
            'otp_code.size' => $this->msg('The OTP code must be 4 characters.', 'يجب أن يكون رمز OTP 4 أحرف.'),
            'device_token.string' => $this->msg('The device token must be a string.', 'يجب أن يكون رمز الجهاز نصاً.'),
        ];
    }
}

