<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Support\Facades\Auth;

class UpdateProfileRequest extends MobileFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customer = Auth::guard('sanctum')->user();
        $customerId = $customer ? $customer->id : null;
        
        return [
            'name' => 'nullable|string|max:255',
            'email' => [
                'nullable',
                'email',
                'max:255',
                $customerId ? 'unique:customers,email,' . $customerId : 'unique:customers,email',
            ],
            'phone' => [
                'nullable',
                'string',
                'max:255',
                $customerId ? 'unique:customers,phone,' . $customerId : 'unique:customers,phone',
            ],
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
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
            'name.required' => $this->msg('The name field is required.', 'حقل الاسم مطلوب.'),
            'name.string' => $this->msg('The name must be a string.', 'يجب أن يكون الاسم نصاً.'),
            'name.max' => $this->msg('The name may not be greater than 255 characters.', 'الاسم لا يجوز أن يتجاوز 255 حرفاً.'),
            'email.required' => $this->msg('The email field is required.', 'حقل البريد الإلكتروني مطلوب.'),
            'email.email' => $this->msg('The email must be a valid email address.', 'يجب أن يكون البريد الإلكتروني صالحاً.'),
            'email.max' => $this->msg('The email may not be greater than 255 characters.', 'البريد الإلكتروني لا يجوز أن يتجاوز 255 حرفاً.'),
            'email.unique' => $this->msg('The email has already been taken.', 'البريد الإلكتروني مستخدم مسبقاً.'),
            'phone.required' => $this->msg('The phone field is required.', 'رقم الهاتف مطلوب.'),
            'phone.string' => $this->msg('The phone must be a string.', 'يجب أن يكون رقم الهاتف نصاً.'),
            'phone.max' => $this->msg('The phone may not be greater than 255 characters.', 'رقم الهاتف لا يجوز أن يتجاوز 255 حرفاً.'),
            'phone.unique' => $this->msg('The phone has already been taken.', 'رقم الهاتف مستخدم مسبقاً.'),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => $this->msg('name', 'الاسم'),
            'email' => $this->msg('email', 'البريد الإلكتروني'),
            'phone' => $this->msg('phone', 'رقم الهاتف'),
        ];
    }
}
