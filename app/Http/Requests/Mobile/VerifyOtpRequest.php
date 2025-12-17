<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'verification_token' => ['required', 'string', 'uuid'],
            'otp_code' => ['required', 'string', 'size:4'],
            'device_token' => ['nullable', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'verification_token.required' => 'The verification token is required.',
            'verification_token.uuid' => 'The verification token must be a valid UUID.',
            'otp_code.required' => 'The OTP code is required.',
            'otp_code.string' => 'The OTP code must be a string.',
            'otp_code.size' => 'The OTP code must be 4 characters.',
            'device_token.string' => 'The device token must be a string.',
        ];
    }
}

