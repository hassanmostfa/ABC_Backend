<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface
{
    /**
     * Get all products with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get all products
     */
    public function getAll(): Collection;

    /**
     * Get product by ID
     */
    public function findById(int $id): ?Product;

    /**
     * Get product by SKU
     */
    public function findBySku(string $sku): ?Product;

    /**
     * Create a new product
     */
    public function create(array $data): Product;

    /**
     * Update product
     */
    public function update(int $id, array $data): ?Product;

    /**
     * Delete product
     */
    public function delete(int $id): bool;

    /**
     * Get active products only
     */
    public function getActive(): Collection;

    /**
     * Get inactive products only
     */
    public function getInactive(): Collection;

    /**
     * Get products by category
     */
    public function getByCategory(int $categoryId): Collection;

    /**
     * Get products by subcategory
     */
    public function getBySubcategory(int $subcategoryId): Collection;

    /**
     * Get products with variants
     */
    public function getWithVariants(): Collection;

    /**
     * Get products without variants
     */
    public function getWithoutVariants(): Collection;
}
