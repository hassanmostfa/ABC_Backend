<?php

namespace App\Http\Requests\Mobile;

class ChangePasswordRequest extends MobileFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'old_password.required' => $this->msg('The current password is required.', 'كلمة المرور الحالية مطلوبة.'),
            'new_password.required' => $this->msg('The new password is required.', 'كلمة المرور الجديدة مطلوبة.'),
            'new_password.min' => $this->msg('The new password must be at least 8 characters.', 'كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل.'),
            'new_password.confirmed' => $this->msg('The new password confirmation does not match.', 'تأكيد كلمة المرور الجديدة غير متطابق.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'old_password' => $this->msg('current password', 'كلمة المرور الحالية'),
            'new_password' => $this->msg('new password', 'كلمة المرور الجديدة'),
        ];
    }
}
