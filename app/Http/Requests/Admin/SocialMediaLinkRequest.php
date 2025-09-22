<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SocialMediaLinkRequest extends FormRequest
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
        $socialMediaLinkId = $this->route('social_media_link') ?? $this->route('id');
        
        return [
            'icon' => 'required|string|max:255',
            'url' => 'required|url|max:500',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'icon.required' => 'Icon is required.',
            'icon.string' => 'Icon must be a string.',
            'icon.max' => 'Icon must not exceed 255 characters.',
            'url.required' => 'URL is required.',
            'url.url' => 'URL must be a valid URL.',
            'url.max' => 'URL must not exceed 500 characters.',
            'is_active.boolean' => 'Active status must be true or false.',
        ];
    }
}
