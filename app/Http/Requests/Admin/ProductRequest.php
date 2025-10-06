<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
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
        $productId = $this->route('id'); // Get the product ID from the route for update operations

        return [
            'name_en' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'category_id' => 'required|integer|exists:categories,id',
            'subcategory_id' => 'nullable|integer|exists:subcategories,id',
            'description_en' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'sku' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'sku')->ignore($productId)
            ],
            'is_active' => 'boolean',
            'variants' => 'required|array|min:1',
            'variants.*.size' => 'nullable|string|max:100',
            'variants.*.sku' => 'required|string|max:255',
            'variants.*.short_item' => 'nullable|string|max:255',
            'variants.*.quantity' => 'required|integer|min:0',
            'variants.*.price' => 'required|numeric|min:0',
            'variants.*.image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'variants.*.is_active' => 'boolean',
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
            'name_en.required' => 'The English name is required.',
            'name_en.max' => 'The English name may not be greater than 255 characters.',
            'name_ar.required' => 'The Arabic name is required.',
            'name_ar.max' => 'The Arabic name may not be greater than 255 characters.',
            'category_id.required' => 'The category is required.',
            'category_id.integer' => 'The category must be a valid ID.',
            'category_id.exists' => 'The selected category does not exist.',
            'subcategory_id.integer' => 'The subcategory must be a valid ID.',
            'subcategory_id.exists' => 'The selected subcategory does not exist.',
            'sku.required' => 'The SKU is required.',
            'sku.unique' => 'The SKU has already been taken.',
            'sku.max' => 'The SKU may not be greater than 255 characters.',
            'is_active.boolean' => 'The is active field must be true or false.',
            'variants.required' => 'At least one variant is required.',
            'variants.array' => 'The variants must be an array.',
            'variants.min' => 'At least one variant is required.',
            'variants.*.size.max' => 'The variant size may not be greater than 100 characters.',
            'variants.*.sku.required' => 'The variant SKU is required.',
            'variants.*.sku.max' => 'The variant SKU may not be greater than 255 characters.',
            'variants.*.short_item.max' => 'The variant short item may not be greater than 255 characters.',
            'variants.*.quantity.required' => 'The variant quantity is required.',
            'variants.*.quantity.integer' => 'The variant quantity must be an integer.',
            'variants.*.quantity.min' => 'The variant quantity must be at least 0.',
            'variants.*.price.required' => 'The variant price is required.',
            'variants.*.price.numeric' => 'The variant price must be a number.',
            'variants.*.price.min' => 'The variant price must be at least 0.',
            'variants.*.image.image' => 'The variant image must be an image file.',
            'variants.*.image.mimes' => 'The variant image must be a file of type: jpeg, png, jpg, gif, svg.',
            'variants.*.image.max' => 'The variant image may not be greater than 2048 kilobytes.',
            'variants.*.is_active.boolean' => 'The variant is active field must be true or false.',
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
            'name_en' => 'English name',
            'name_ar' => 'Arabic name',
            'category_id' => 'category',
            'subcategory_id' => 'subcategory',
            'description_en' => 'English description',
            'description_ar' => 'Arabic description',
            'is_active' => 'is active',
            'variants.*.size' => 'variant size',
            'variants.*.sku' => 'variant SKU',
            'variants.*.short_item' => 'variant short item',
            'variants.*.quantity' => 'variant quantity',
            'variants.*.price' => 'variant price',
            'variants.*.image' => 'variant image',
            'variants.*.is_active' => 'variant is active',
        ];
    }
}
