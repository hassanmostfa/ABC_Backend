<?php

namespace App\Repositories;

use App\Models\Area;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface AreaRepositoryInterface
{
    /**
     * Get all areas with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get all areas
     */
    public function getAll(): Collection;

    /**
     * Get area by ID
     */
    public function findById(int $id): ?Area;

    /**
     * Get areas by governorate ID
     */
    public function getByGovernorate(int $governorateId): Collection;

    /**
     * Get areas by country ID
     */
    public function getByCountry(int $countryId): Collection;

    /**
     * Create a new area
     */
    public function create(array $data): Area;

    /**
     * Update area
     */
    public function update(int $id, array $data): ?Area;

    /**
     * Delete area
     */
    public function delete(int $id): bool;
}
