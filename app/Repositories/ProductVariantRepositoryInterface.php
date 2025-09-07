<?php

namespace App\Repositories;

use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProductVariantRepositoryInterface
{
    /**
     * Get all product variants with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get all product variants
     */
    public function getAll(): Collection;

    /**
     * Get product variant by ID
     */
    public function findById(int $id): ?ProductVariant;

    /**
     * Get product variant by SKU
     */
    public function findBySku(string $sku): ?ProductVariant;

    /**
     * Create a new product variant
     */
    public function create(array $data): ProductVariant;

    /**
     * Update product variant
     */
    public function update(int $id, array $data): ?ProductVariant;

    /**
     * Delete product variant
     */
    public function delete(int $id): bool;

    /**
     * Get active product variants only
     */
    public function getActive(): Collection;

    /**
     * Get inactive product variants only
     */
    public function getInactive(): Collection;

    /**
     * Get variants by product ID
     */
    public function getByProduct(int $productId): Collection;

    /**
     * Get variants by variant type
     */
    public function getByVariantType(string $variantType): Collection;

    /**
     * Get variants by product and type
     */
    public function getByProductAndType(int $productId, string $variantType): Collection;
}
