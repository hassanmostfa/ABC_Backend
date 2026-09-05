<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductPackagingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $packagingId = $this->route('id');
        $isUpdate = !is_null($packagingId);

        $nameRules = ['required', 'string', 'max:255', Rule::unique('product_packagings', 'name')->ignore($packagingId ? (int) $packagingId : null)];

        if ($isUpdate) {
            array_unshift($nameRules, 'sometimes');
        }

        return [
            'name' => $nameRules,
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The packaging name is required.',
            'name.unique' => 'This packaging already exists.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('name') && is_string($this->input('name'))) {
            $data['name'] = trim($this->input('name'));
        }

        if ($this->exists('is_active')) {
            $value = $this->input('is_active');

            if ($value === '' || $value === null) {
                $data['is_active'] = true;
            } elseif (is_string($value)) {
                $data['is_active'] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }
}
