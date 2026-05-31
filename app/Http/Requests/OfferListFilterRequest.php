<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OfferListFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'category_id' => $this->normalizeOptionalId($this->input('category_id') ?? $this->input('category')),
            'subcategory_id' => $this->normalizeOptionalId($this->input('subcategory_id') ?? $this->input('subcategory')),
        ]);
    }

    public function rules(): array
    {
        return [
            'per_page' => 'nullable|integer|min:1|max:100',
            'type' => 'nullable|in:normal,charity',
            'category_id' => 'nullable|integer|min:1',
            'subcategory_id' => 'nullable|integer|min:1',
            'stock_status' => 'nullable|in:in_stock,out_of_stock',
            'search' => 'nullable|string|max:1000',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function offerFilters(bool $activeOnly = false): array
    {
        $filters = [
            'type' => $this->input('type'),
            'category_id' => $this->input('category_id'),
            'subcategory_id' => $this->input('subcategory_id'),
            'stock_status' => $this->input('stock_status'),
            'search' => $this->input('search'),
        ];

        if ($activeOnly) {
            $filters['active_only'] = true;
        }

        return array_filter($filters, function ($value, $key) {
            if ($key === 'active_only') {
                return $value === true;
            }

            return $value !== null && $value !== '';
        }, ARRAY_FILTER_USE_BOTH);
    }

    private function normalizeOptionalId(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === 'null' || $value === 'undefined' || $value === 'all') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
