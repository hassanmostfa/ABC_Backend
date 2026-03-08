<?php

namespace App\Http\Requests\Mobile;

class CompleteRegistrationRequest extends MobileFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:customers,email'],
            'current_language' => ['required', 'in:en,ar'],
            'device_token' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => $this->msg('The name field is required.', 'حقل الاسم مطلوب.'),
            'name.string' => $this->msg('The name must be a string.', 'يجب أن يكون الاسم نصاً.'),
            'name.max' => $this->msg('The name may not be greater than 255 characters.', 'الاسم لا يجوز أن يتجاوز 255 حرفاً.'),
            'email.email' => $this->msg('The email must be a valid email address.', 'يجب أن يكون البريد الإلكتروني صالحاً.'),
            'email.max' => $this->msg('The email may not be greater than 255 characters.', 'البريد الإلكتروني لا يجوز أن يتجاوز 255 حرفاً.'),
            'email.unique' => $this->msg('The email has already been taken.', 'البريد الإلكتروني مستخدم مسبقاً.'),
            'current_language.required' => $this->msg('The current language field is required.', 'حقل اللغة مطلوب.'),
            'current_language.in' => $this->msg('The current language must be either en or ar.', 'اللغة يجب أن تكون en أو ar.'),
            'device_token.required' => $this->msg('The device token field is required.', 'رمز الجهاز مطلوب.'),
            'device_token.string' => $this->msg('The device token must be a string.', 'يجب أن يكون رمز الجهاز نصاً.'),
            'device_token.max' => $this->msg('The device token may not be greater than 1000 characters.', 'رمز الجهاز لا يجوز أن يتجاوز 1000 حرف.'),
        ];
    }
}

