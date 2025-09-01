<?php

namespace App\Repositories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface CategoryRepositoryInterface
{
    /**
     * Get all categories with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get all categories
     */
    public function getAll(): Collection;

    /**
     * Get category by ID
     */
    public function findById(int $id): ?Category;

    /**
     * Create a new category
     */
    public function create(array $data): Category;

    /**
     * Update category
     */
    public function update(int $id, array $data): ?Category;

    /**
     * Delete category
     */
    public function delete(int $id): bool;

    /**
     * Get active categories only
     */
    public function getActive(): Collection;

    /**
     * Get inactive categories only
     */
    public function getInactive(): Collection;
}
