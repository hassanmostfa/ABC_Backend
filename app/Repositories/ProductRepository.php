<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductRepository implements ProductRepositoryInterface
{
    protected $model;

    public function __construct(Product $product)
    {
        $this->model = $product;
    }

    /**
     * Get all products with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with(['category', 'subcategory']);

        // Search functionality
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name_en', 'LIKE', "%{$search}%")
                  ->orWhere('name_ar', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%")
                  ->orWhere('short_item', 'LIKE', "%{$search}%");
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

        // Filter by category
        if (isset($filters['category_id']) && !empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // Filter by subcategory
        if (isset($filters['subcategory_id']) && !empty($filters['subcategory_id'])) {
            $query->where('subcategory_id', $filters['subcategory_id']);
        }

        // Filter by variants
        if (isset($filters['has_variants']) && $filters['has_variants'] !== '') {
            $query->where('has_variants', $filters['has_variants'] === 'true');
        }

        // Filter by price range
        if (isset($filters['min_price']) && !empty($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price']) && !empty($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        // Default sorting by created_at desc
        $query->orderBy('created_at', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get all products
     */
    public function getAll(): Collection
    {
        return $this->model->with(['category', 'subcategory'])->get();
    }

    /**
     * Get product by ID
     */
    public function findById(int $id): ?Product
    {
        return $this->model->with(['category', 'subcategory'])->find($id);
    }

    /**
     * Get product by SKU
     */
    public function findBySku(string $sku): ?Product
    {
        return $this->model->with(['category', 'subcategory'])->where('sku', $sku)->first();
    }

    /**
     * Create a new product
     */
    public function create(array $data): Product
    {
        return $this->model->create($data);
    }

    /**
     * Update product
     */
    public function update(int $id, array $data): ?Product
    {
        $product = $this->model->find($id);
        
        if (!$product) {
            return null;
        }

        $product->update($data);
        return $product->load(['category', 'subcategory']);
    }

    /**
     * Delete product
     */
    public function delete(int $id): bool
    {
        $product = $this->model->find($id);
        
        if (!$product) {
            return false;
        }

        return $product->delete();
    }

    /**
     * Get active products only
     */
    public function getActive(): Collection
    {
        return $this->model->with(['category', 'subcategory'])
                          ->where('is_active', true)
                          ->get();
    }

    /**
     * Get inactive products only
     */
    public function getInactive(): Collection
    {
        return $this->model->with(['category', 'subcategory'])
                          ->where('is_active', false)
                          ->get();
    }

    /**
     * Get products by category
     */
    public function getByCategory(int $categoryId): Collection
    {
        return $this->model->with(['category', 'subcategory'])
                          ->where('category_id', $categoryId)
                          ->get();
    }

    /**
     * Get products by subcategory
     */
    public function getBySubcategory(int $subcategoryId): Collection
    {
        return $this->model->with(['category', 'subcategory'])
                          ->where('subcategory_id', $subcategoryId)
                          ->get();
    }

    /**
     * Get products with variants
     */
    public function getWithVariants(): Collection
    {
        return $this->model->with(['category', 'subcategory'])
                          ->where('has_variants', true)
                          ->get();
    }

    /**
     * Get products without variants
     */
    public function getWithoutVariants(): Collection
    {
        return $this->model->with(['category', 'subcategory'])
                          ->where('has_variants', false)
                          ->get();
    }
}
