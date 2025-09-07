<?php

namespace App\Repositories;

use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductVariantRepository implements ProductVariantRepositoryInterface
{
    protected $model;

    public function __construct(ProductVariant $productVariant)
    {
        $this->model = $productVariant;
    }

    /**
     * Get all product variants with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with(['product']);

        // Search functionality
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('variant_name_en', 'LIKE', "%{$search}%")
                  ->orWhere('variant_name_ar', 'LIKE', "%{$search}%")
                  ->orWhere('variant_value_en', 'LIKE', "%{$search}%")
                  ->orWhere('variant_value_ar', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        // Filter by status
        if (isset($filters['status']) && $filters['status'] !== '') {
            if ($filters['status'] === 'active') {
                $query->where('is_active', true);
            } elseif ($filters['status'] === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Filter by product
        if (isset($filters['product_id']) && !empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        // Filter by variant type
        if (isset($filters['variant_type']) && !empty($filters['variant_type'])) {
            $query->where('variant_type', $filters['variant_type']);
        }

        // Filter by price adjustment range
        if (isset($filters['min_price_adjustment']) && !empty($filters['min_price_adjustment'])) {
            $query->where('price_adjustment', '>=', $filters['min_price_adjustment']);
        }

        if (isset($filters['max_price_adjustment']) && !empty($filters['max_price_adjustment'])) {
            $query->where('price_adjustment', '<=', $filters['max_price_adjustment']);
        }

        // Default sorting by created_at desc
        $query->orderBy('created_at', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get all product variants
     */
    public function getAll(): Collection
    {
        return $this->model->with(['product'])->get();
    }

    /**
     * Get product variant by ID
     */
    public function findById(int $id): ?ProductVariant
    {
        return $this->model->with(['product'])->find($id);
    }

    /**
     * Get product variant by SKU
     */
    public function findBySku(string $sku): ?ProductVariant
    {
        return $this->model->with(['product'])->where('sku', $sku)->first();
    }

    /**
     * Create a new product variant
     */
    public function create(array $data): ProductVariant
    {
        return $this->model->create($data);
    }

    /**
     * Update product variant
     */
    public function update(int $id, array $data): ?ProductVariant
    {
        $variant = $this->model->find($id);
        
        if (!$variant) {
            return null;
        }

        $variant->update($data);
        return $variant->load(['product']);
    }

    /**
     * Delete product variant
     */
    public function delete(int $id): bool
    {
        $variant = $this->model->find($id);
        
        if (!$variant) {
            return false;
        }

        return $variant->delete();
    }

    /**
     * Get active product variants only
     */
    public function getActive(): Collection
    {
        return $this->model->with(['product'])
                          ->where('is_active', true)
                          ->get();
    }

    /**
     * Get inactive product variants only
     */
    public function getInactive(): Collection
    {
        return $this->model->with(['product'])
                          ->where('is_active', false)
                          ->get();
    }

    /**
     * Get variants by product ID
     */
    public function getByProduct(int $productId): Collection
    {
        return $this->model->with(['product'])
                          ->where('product_id', $productId)
                          ->get();
    }

    /**
     * Get variants by variant type
     */
    public function getByVariantType(string $variantType): Collection
    {
        return $this->model->with(['product'])
                          ->where('variant_type', $variantType)
                          ->get();
    }

    /**
     * Get variants by product and type
     */
    public function getByProductAndType(int $productId, string $variantType): Collection
    {
        return $this->model->with(['product'])
                          ->where('product_id', $productId)
                          ->where('variant_type', $variantType)
                          ->get();
    }
}
