<?php

namespace App\Repositories;

use App\Models\Governorate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface GovernorateRepositoryInterface
{
    /**
     * Get all governorates with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get all governorates
     */
    public function getAll(): Collection;

    /**
     * Get governorate by ID
     */
    public function findById(int $id): ?Governorate;

    /**
     * Get governorates by country ID
     */
    public function getByCountry(int $countryId): Collection;

    /**
     * Create a new governorate
     */
    public function create(array $data): Governorate;

    /**
     * Update governorate
     */
    public function update(int $id, array $data): ?Governorate;

    /**
     * Delete governorate
     */
    public function delete(int $id): bool;
}
