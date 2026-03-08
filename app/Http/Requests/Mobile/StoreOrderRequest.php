<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Support\Facades\Auth;

class StoreOrderRequest extends MobileFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customer = Auth::guard('sanctum')->user();
        $request = $this;

        $rules = [
            'charity_id' => 'nullable|integer|exists:charities,id',
            'customer_address_id' => [
                'required',
                'integer',
                'exists:customer_addresses,id',
            ],
            'delivery_type' => 'nullable|in:pickup,delivery',
            'payment_method' => 'nullable|in:cash,card,online_link,bank_transfer,wallet',
            'offer_ids' => 'nullable|array',
            'offer_ids.*' => 'required_with:offer_ids|integer|exists:offers,id',
            'offers' => 'nullable|array',
            'offers.*.offer_id' => 'required_with:offers|integer|exists:offers,id',
            'offers.*.quantity' => 'required_with:offers.*.offer_id|integer|min:1',
            'offer_snapshot' => 'nullable|array',
            'coupons_discount' => 'nullable|numeric|min:0',
            'used_points' => [
                'nullable',
                'integer',
                'min:10',
                function ($attribute, $value, $fail) use ($customer, $request) {
                    if (!$value) {
                        return;
                    }
                    if ($value && $value % 10 !== 0 && $value > 0) {
                        $fail($request->msg('Points must be a multiple of 10.', 'النقاط يجب أن تكون من مضاعفات 10.'));
                    }
                    if ($value && $customer) {
                        $customerPoints = $customer->points ?? 0;
                        if ($customerPoints < $value) {
                            $fail($request->msg('You do not have enough points. Available: ' . $customerPoints, 'النقاط غير كافية. المتاح: ' . $customerPoints));
                        }
                    } elseif ($value && !$customer) {
                        $fail($request->msg('Customer ID is required when using points.', 'معرف العميل مطلوب عند استخدام النقاط.'));
                    }
                },
            ],
            'items' => [
                'required_without_all:offer_ids,offers',
                'nullable',
                'array',
                function ($attribute, $value, $fail) use ($request) {
                    $offerIds = $request->input('offer_ids');
                    $offers = $request->input('offers');
                    $hasOffers = (!empty($offerIds) && is_array($offerIds) && count($offerIds) > 0)
                               || (!empty($offers) && is_array($offers) && count($offers) > 0);
                    if (!$hasOffers) {
                        if (empty($value) || !is_array($value) || count($value) === 0) {
                            $fail($request->msg('Items are required when no offers are provided.', 'المنتجات مطلوبة عند عدم وجود عروض.'));
                        }
                    }
                },
            ],
            'items.*.variant_id' => 'required_with:items|integer|exists:product_variants,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
        ];

        // Validate customer_address_id belongs to authenticated customer
        if ($customer) {
            $rules['customer_address_id'][] = function ($attribute, $value, $fail) use ($customer, $request) {
                $address = \App\Models\CustomerAddress::find($value);
                if ($address && $address->customer_id !== $customer->id) {
                    $fail($request->msg('The selected customer address does not belong to you.', 'العنوان المحدد لا ينتمي إليك.'));
                }
            };
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'charity_id.integer' => $this->msg('The charity ID must be a valid integer.', 'معرف الجمعية يجب أن يكون رقماً صحيحاً.'),
            'charity_id.exists' => $this->msg('The selected charity does not exist.', 'الجمعية المحددة غير موجودة.'),
            'customer_address_id.required' => $this->msg('The customer address ID is required.', 'عنوان التوصيل مطلوب.'),
            'customer_address_id.integer' => $this->msg('The customer address ID must be a valid integer.', 'معرف العنوان يجب أن يكون رقماً صحيحاً.'),
            'customer_address_id.exists' => $this->msg('The selected customer address does not exist.', 'العنوان المحدد غير موجود.'),
            'delivery_type.in' => $this->msg('The delivery type must be either pickup or delivery.', 'نوع التوصيل يجب أن يكون استلام أو توصيل.'),
            'payment_method.in' => $this->msg('The payment method is invalid.', 'طريقة الدفع غير صالحة.'),
            'offer_ids.array' => $this->msg('The offer IDs must be an array.', 'معرفات العروض يجب أن تكون مصفوفة.'),
            'offer_ids.*.integer' => $this->msg('Each offer ID must be a valid integer.', 'كل معرف عرض يجب أن يكون رقماً صحيحاً.'),
            'offer_ids.*.exists' => $this->msg('One or more selected offers do not exist.', 'واحد أو أكثر من العروض المحددة غير موجود.'),
            'offers.array' => $this->msg('The offers must be an array.', 'العروض يجب أن تكون مصفوفة.'),
            'offers.*.offer_id.required' => $this->msg('The offer ID is required for each offer.', 'معرف العرض مطلوب لكل عرض.'),
            'offers.*.offer_id.integer' => $this->msg('Each offer ID must be a valid integer.', 'معرف العرض يجب أن يكون رقماً صحيحاً.'),
            'offers.*.offer_id.exists' => $this->msg('One or more selected offers do not exist.', 'واحد أو أكثر من العروض المحددة غير موجود.'),
            'offers.*.quantity.required' => $this->msg('The quantity is required for each offer.', 'الكمية مطلوبة لكل عرض.'),
            'offers.*.quantity.integer' => $this->msg('The quantity must be a valid integer.', 'الكمية يجب أن تكون رقماً صحيحاً.'),
            'offers.*.quantity.min' => $this->msg('The quantity must be at least 1.', 'الكمية يجب أن تكون 1 على الأقل.'),
            'offer_snapshot.array' => $this->msg('The offer snapshot must be an array.', 'لقطة العرض يجب أن تكون مصفوفة.'),
            'coupons_discount.numeric' => $this->msg('Coupons discount must be a valid number.', 'خصم الكوبونات يجب أن يكون رقماً.'),
            'coupons_discount.min' => $this->msg('Coupons discount cannot be negative.', 'خصم الكوبونات لا يمكن أن يكون سلبياً.'),
            'items.required' => $this->msg('At least one order item is required.', 'مطلوب منتج واحد على الأقل.'),
            'items.array' => $this->msg('The items must be an array.', 'المنتجات يجب أن تكون مصفوفة.'),
            'items.min' => $this->msg('At least one order item is required.', 'مطلوب منتج واحد على الأقل.'),
            'items.*.variant_id.required' => $this->msg('The variant ID is required for each item.', 'معرف المتغير مطلوب لكل منتج.'),
            'items.*.variant_id.integer' => $this->msg('The variant ID must be a valid integer.', 'معرف المتغير يجب أن يكون رقماً صحيحاً.'),
            'items.*.variant_id.exists' => $this->msg('The selected variant does not exist.', 'المتغير المحدد غير موجود.'),
            'items.*.quantity.required' => $this->msg('The quantity is required for each item.', 'الكمية مطلوبة لكل منتج.'),
            'items.*.quantity.integer' => $this->msg('The quantity must be a valid integer.', 'الكمية يجب أن تكون رقماً صحيحاً.'),
            'items.*.quantity.min' => $this->msg('The quantity must be at least 1.', 'الكمية يجب أن تكون 1 على الأقل.'),
            'used_points.integer' => $this->msg('The used points must be a valid integer.', 'النقاط المستخدمة يجب أن تكون رقماً صحيحاً.'),
            'used_points.min' => $this->msg('The minimum points to use is 10.', 'الحد الأدنى للنقاط المستخدمة هو 10.'),
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
            'charity_id' => 'charity',
            'customer_address_id' => 'customer address',
            'delivery_type' => 'delivery type',
            'payment_method' => 'payment method',
            'offer_snapshot' => 'offer snapshot',
            'offers' => 'offers',
            'offers.*.offer_id' => 'offer ID',
            'offers.*.quantity' => 'quantity',
            'items' => 'order items',
            'items.*.variant_id' => 'variant',
            'items.*.quantity' => 'quantity',
            'used_points' => 'used points',
        ];
    }
}
