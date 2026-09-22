<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isCreate = $this->isMethod('POST') && $this->route('id') === null;

        return [
            'name' => ($isCreate ? 'required|' : 'sometimes|') . 'string|max:255',
            'image' => ($isCreate ? 'required|' : 'nullable|') . 'image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'price' => ($isCreate ? 'required|' : 'sometimes|') . 'numeric|min:0.001',
            'service_provider_email' => ($isCreate ? 'required|' : 'sometimes|') . 'email|max:255',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Service name is required.',
            'name.string' => 'Service name must be text.',
            'name.max' => 'Service name may not be greater than 255 characters.',
            'image.required' => 'Service image is required.',
            'image.image' => 'The file must be an image.',
            'image.mimes' => 'The image must be a file of type: jpeg, png, jpg, gif, webp.',
            'image.max' => 'The image must not exceed 5120 kilobytes.',
            'price.required' => 'Service price is required.',
            'price.numeric' => 'Service price must be a number.',
            'price.min' => 'Service price must be at least 0.001.',
            'service_provider_email.required' => 'Service provider email is required.',
            'service_provider_email.email' => 'Service provider email must be a valid email address.',
            'service_provider_email.max' => 'Service provider email may not be greater than 255 characters.',
            'is_active.boolean' => 'The is_active field must be true or false.',
        ];
    }
}
