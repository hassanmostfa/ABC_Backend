<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CareerRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'applying_position' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
            'file' => 'required|file|mimes:pdf,doc,docx,txt|max:10240', // File upload (10MB max)
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Name is required.',
            'name.string' => 'Name must be a string.',
            'name.max' => 'Name must not exceed 255 characters.',
            'email.required' => 'Email is required.',
            'email.email' => 'Email must be a valid email address.',
            'email.max' => 'Email must not exceed 255 characters.',
            'phone.required' => 'Phone number is required.',
            'phone.string' => 'Phone number must be a string.',
            'phone.max' => 'Phone number must not exceed 20 characters.',
            'applying_position.required' => 'Applying position is required.',
            'applying_position.string' => 'Applying position must be a string.',
            'applying_position.max' => 'Applying position must not exceed 255 characters.',
            'message.required' => 'Message is required.',
            'message.string' => 'Message must be a string.',
            'message.max' => 'Message must not exceed 2000 characters.',
            'file.required' => 'File is required.',
            'file.file' => 'الملف يجب أن يكون ملف صحيح.',
            'file.mimes' => 'الملف يجب أن يكون من نوع: pdf, doc, docx, txt.',
            'file.max' => 'حجم الملف لا يجب أن يتجاوز 10240 كيلوبايت.',
        ];
    }
}
