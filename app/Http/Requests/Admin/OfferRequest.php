<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\ProductVariant;
use App\Models\Product;

class OfferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the response that should be returned if validation fails.
     */
    public function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422)
        );
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        // Handle JSON strings for conditions and rewards
        if ($this->has('conditions') && is_string($this->conditions)) {
            $decoded = json_decode($this->conditions, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['conditions' => $decoded]);
            }
        }
        
        if ($this->has('rewards') && is_string($this->rewards)) {
            $decoded = json_decode($this->rewards, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['rewards' => $decoded]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'offer_start_date' => 'required|date|after_or_equal:today',
            'offer_end_date' => 'required|date|after:offer_start_date',
            'is_active' => 'boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'type' => 'nullable|string|max:100',
            'points' => 'nullable|integer|min:0',
            'charity_id' => 'nullable|integer|exists:charities,id',
            'reward_type' => 'required|in:products,discount',
            'conditions' => 'required|array|min:1',
            'conditions.*.product_id' => 'required|integer|exists:products,id',
            'conditions.*.product_variant_id' => 'nullable|integer|exists:product_variants,id',
            'conditions.*.quantity' => [
                'required',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) {
                    $this->validateQuantityAvailability($attribute, $value, $fail, 'condition');
                }
            ],
            'conditions.*.is_active' => 'boolean',
        ];

        // Add reward validation based on reward_type
        if ($this->input('reward_type') === 'products') {
            $rules['rewards'] = 'required|array|min:1';
            $rules['rewards.*.product_id'] = 'nullable|integer|exists:products,id';
            $rules['rewards.*.product_variant_id'] = 'nullable|integer|exists:product_variants,id';
            $rules['rewards.*.quantity'] = [
                'required',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) {
                    $this->validateQuantityAvailability($attribute, $value, $fail, 'reward');
                }
            ];
            $rules['rewards.*.discount_amount'] = 'nullable|numeric|min:0';
            $rules['rewards.*.discount_type'] = 'nullable|in:percentage,fixed';
            $rules['rewards.*.is_active'] = 'boolean';
        } else {
            // For discount type, rewards are optional or can be empty
            $rules['rewards'] = 'nullable|array';
            $rules['rewards.*.product_id'] = 'nullable|integer|exists:products,id';
            $rules['rewards.*.product_variant_id'] = 'nullable|integer|exists:product_variants,id';
            $rules['rewards.*.quantity'] = 'nullable|integer|min:1';
            $rules['rewards.*.discount_amount'] = 'nullable|numeric|min:0';
            $rules['rewards.*.discount_type'] = 'nullable|in:percentage,fixed';
            $rules['rewards.*.is_active'] = 'boolean';
        }

        return $rules;
    }

    /**
     * Validate quantity availability for product variants
     */
    private function validateQuantityAvailability($attribute, $value, $fail, $type)
    {
        // Extract the index from the attribute (e.g., "conditions.0.quantity" -> 0)
        $attributeParts = explode('.', $attribute);
        $index = $attributeParts[1];
        
        // Get the data for this condition/reward
        $data = $this->input($type === 'condition' ? 'conditions' : 'rewards');
        $itemData = $data[$index] ?? null;
        
        if (!$itemData) {
            return; // Skip validation if data is not available
        }
        
        $productId = $itemData['product_id'] ?? null;
        $productVariantId = $itemData['product_variant_id'] ?? null;
        $requestedQuantity = (int) $value;
        
        if ($productVariantId) {
            // Check product variant quantity
            $variant = ProductVariant::find($productVariantId);
            if (!$variant) {
                $fail("متغير المنتج المحدد غير موجود.");
                return;
            }
            
            if ($variant->quantity < $requestedQuantity) {
                $fail("الكمية المتاحة غير كافية. المتاح: {$variant->quantity}, المطلوب: {$requestedQuantity}");
                return;
            }
        } else if ($productId) {
            // Check product quantity (when no variant is selected but product_id exists)
            $product = Product::find($productId);
            if (!$product) {
                $fail("المنتج المحدد غير موجود.");
                return;
            }
            
            if ($product->quantity < $requestedQuantity) {
                $fail("الكمية المتاحة غير كافية. المتاح: {$product->quantity}, المطلوب: {$requestedQuantity}");
                return;
            }
        } else if ($type === 'reward') {
            // For rewards, if both product_id and product_variant_id are null, 
            // this might be a discount-only reward, so we skip quantity validation
            return;
        } else {
            // For conditions, product_id is required
            $fail("منتج الشرط مطلوب.");
            return;
        }
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'offer_start_date.required' => 'تاريخ بداية العرض مطلوب.',
            'offer_start_date.date' => 'تاريخ بداية العرض يجب أن يكون تاريخ صحيح.',
            'offer_start_date.after_or_equal' => 'تاريخ بداية العرض يجب أن يكون اليوم أو في المستقبل.',
            'offer_end_date.required' => 'تاريخ انتهاء العرض مطلوب.',
            'offer_end_date.date' => 'تاريخ انتهاء العرض يجب أن يكون تاريخ صحيح.',
            'offer_end_date.after' => 'تاريخ انتهاء العرض يجب أن يكون بعد تاريخ البداية.',
            'is_active.boolean' => 'حالة التفعيل يجب أن تكون صحيحة أو خاطئة.',
            'image.image' => 'الصورة يجب أن تكون ملف صورة صحيح.',
            'image.mimes' => 'الصورة يجب أن تكون من نوع: jpeg, png, jpg, gif, webp.',
            'image.max' => 'حجم الصورة لا يجب أن يتجاوز 2048 كيلوبايت.',
            'type.max' => 'النوع لا يجب أن يتجاوز 100 حرف.',
            'points.integer' => 'النقاط يجب أن تكون رقم صحيح.',
            'points.min' => 'النقاط يجب أن تكون على الأقل 0.',
            'charity_id.integer' => 'الجمعية الخيرية يجب أن تكون معرف صحيح.',
            'charity_id.exists' => 'الجمعية الخيرية المحددة غير موجودة.',
            'reward_type.required' => 'نوع المكافأة مطلوب.',
            'reward_type.in' => 'نوع المكافأة يجب أن يكون منتجات أو خصم.',
            'conditions.required' => 'شرط واحد على الأقل مطلوب.',
            'conditions.array' => 'الشروط يجب أن تكون مصفوفة.',
            'conditions.min' => 'شرط واحد على الأقل مطلوب.',
            'conditions.*.product_id.required' => 'منتج الشرط مطلوب.',
            'conditions.*.product_id.exists' => 'منتج الشرط المحدد غير موجود.',
            'conditions.*.product_variant_id.exists' => 'متغير منتج الشرط المحدد غير موجود.',
            'conditions.*.quantity.required' => 'كمية الشرط مطلوبة.',
            'conditions.*.quantity.min' => 'كمية الشرط يجب أن تكون على الأقل 1.',
            'rewards.required' => 'مكافأة واحدة على الأقل مطلوبة.',
            'rewards.array' => 'المكافآت يجب أن تكون مصفوفة.',
            'rewards.min' => 'مكافأة واحدة على الأقل مطلوبة.',
            'rewards.*.product_id.exists' => 'منتج المكافأة المحدد غير موجود.',
            'rewards.*.product_variant_id.exists' => 'متغير منتج المكافأة المحدد غير موجود.',
            'rewards.*.quantity.required' => 'كمية المكافأة مطلوبة.',
            'rewards.*.quantity.min' => 'كمية المكافأة يجب أن تكون على الأقل 1.',
            'rewards.*.discount_amount.numeric' => 'مبلغ الخصم يجب أن يكون رقم.',
            'rewards.*.discount_amount.min' => 'مبلغ الخصم يجب أن يكون على الأقل 0.',
            'rewards.*.discount_type.in' => 'نوع الخصم يجب أن يكون نسبة مئوية أو مبلغ ثابت.',
            'conditions.*.quantity.insufficient' => 'الكمية المتاحة غير كافية للمتغير المحدد.',
            'rewards.*.quantity.insufficient' => 'الكمية المتاحة غير كافية للمتغير المحدد.',
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
            'offer_start_date' => 'تاريخ بداية العرض',
            'offer_end_date' => 'تاريخ انتهاء العرض',
            'is_active' => 'حالة التفعيل',
            'image' => 'الصورة',
            'type' => 'النوع',
            'points' => 'النقاط',
            'charity_id' => 'الجمعية الخيرية',
            'reward_type' => 'نوع المكافأة',
            'conditions' => 'الشروط',
            'rewards' => 'المكافآت',
        ];
    }
}
