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
            'title_en' => 'nullable|string|max:255',
            'title_ar' => 'nullable|string|max:255',
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
            'icon.required' => 'الأيقونة مطلوبة.',
            'icon.image' => 'الأيقونة يجب أن تكون ملف صورة صحيح.',
            'icon.mimes' => 'الأيقونة يجب أن تكون من نوع: jpeg, png, jpg, gif, svg.',
            'icon.max' => 'حجم الأيقونة لا يجب أن يتجاوز 2048 كيلوبايت.',
            'title_en.max' => 'العنوان بالإنجليزية لا يجب أن يتجاوز 255 حرف.',
            'title_ar.max' => 'العنوان بالعربية لا يجب أن يتجاوز 255 حرف.',
            'url.required' => 'الرابط مطلوب.',
            'url.url' => 'الرابط يجب أن يكون رابط صحيح.',
            'url.max' => 'الرابط لا يجب أن يتجاوز 500 حرف.',
            'is_active.boolean' => 'حالة التفعيل يجب أن تكون صحيحة أو خاطئة.',
        ];
    }
}
