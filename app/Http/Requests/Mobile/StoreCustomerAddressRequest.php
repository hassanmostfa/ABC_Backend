<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Validation\Rule;

class StoreCustomerAddressRequest extends MobileFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'country_id' => 'required|integer|exists:countries,id',
            'governorate_id' => 'required|integer|exists:governorates,id',
            'area_id' => 'required|integer|exists:areas,id',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'type' => ['required', 'string', Rule::in(['apartment', 'house', 'office'])],
            'phone_number' => 'required|string|max:50',
            'additional_directions' => 'nullable|string|max:500',
            'address_label' => 'nullable|string|max:100',
        ];

        $type = $this->input('type');

        if ($type === 'apartment') {
            $rules['building_name'] = 'required|string|max:255';
            $rules['apartment_number'] = 'required|string|max:50';
            $rules['floor'] = 'required|string|max:50';
            $rules['street'] = 'required|string|max:255';
        } elseif ($type === 'house') {
            $rules['house'] = 'required|string|max:255';
            $rules['street'] = 'required|string|max:255';
            $rules['block'] = 'required|string|max:255';
        } elseif ($type === 'office') {
            $rules['building_name'] = 'required|string|max:255';
            $rules['company'] = 'required|string|max:255';
            $rules['floor'] = 'required|string|max:50';
            $rules['street'] = 'required|string|max:255';
            $rules['block'] = 'required|string|max:255';
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
            'country_id.required' => $this->msg('The country is required.', 'الدولة مطلوبة.'),
            'governorate_id.required' => $this->msg('The governorate is required.', 'المحافظة مطلوبة.'),
            'area_id.required' => $this->msg('The area is required.', 'المنطقة مطلوبة.'),
            'lat.required' => $this->msg('The latitude is required.', 'خط العرض مطلوب.'),
            'lng.required' => $this->msg('The longitude is required.', 'خط الطول مطلوب.'),
            'type.required' => $this->msg('The address type is required.', 'نوع العنوان مطلوب.'),
            'type.in' => $this->msg('The address type must be apartment, house, or office.', 'نوع العنوان يجب أن يكون شقة أو منزل أو مكتب.'),
            'phone_number.required' => $this->msg('The phone number is required.', 'رقم الهاتف مطلوب.'),
            'building_name.required' => $this->msg('The building name is required.', 'اسم المبنى مطلوب.'),
            'apartment_number.required' => $this->msg('The apartment number is required.', 'رقم الشقة مطلوب.'),
            'floor.required' => $this->msg('The floor is required.', 'الطابق مطلوب.'),
            'street.required' => $this->msg('The street is required.', 'الشارع مطلوب.'),
            'house.required' => $this->msg('The house is required.', 'المنزل مطلوب.'),
            'block.required' => $this->msg('The block is required.', 'القطعة مطلوبة.'),
            'company.required' => $this->msg('The company name is required.', 'اسم الشركة مطلوب.'),
        ];
    }
}
