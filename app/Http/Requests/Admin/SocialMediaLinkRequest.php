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
            'icon' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
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
            'icon.image' => 'Icon must be an image file.',
            'icon.mimes' => 'Icon must be a file of type: jpeg, png, jpg, gif, svg.',
            'icon.max' => 'Icon may not be greater than 2048 kilobytes.',
            'url.required' => 'URL is required.',
            'url.url' => 'URL must be a valid URL.',
            'url.max' => 'URL must not exceed 500 characters.',
            'is_active.boolean' => 'Active status must be true or false.',
        ];
    }
}
