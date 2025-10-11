<?php

namespace App\Repositories;

use App\Models\Charity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface CharityRepositoryInterface
{
    /**
     * Get all charities with pagination
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get all charities
     */
    public function getAll(): Collection;

    /**
     * Get all charities with filters (no pagination)
     */
    public function getAllWithFilters(array $filters = []): Collection;

    /**
     * Get charity by ID
     */
    public function findById(int $id): ?Charity;

    /**
     * Create a new charity
     */
    public function create(array $data): Charity;

    /**
     * Update charity
     */
    public function update(int $id, array $data): ?Charity;

    /**
     * Delete charity
     */
    public function delete(int $id): bool;
}
