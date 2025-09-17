<?php

namespace App\Repositories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface CountryRepositoryInterface
{
    /**
     * Get all countries with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get all countries
     */
    public function getAll(): Collection;

    /**
     * Get country by ID
     */
    public function findById(int $id): ?Country;

    /**
     * Create a new country
     */
    public function create(array $data): Country;

    /**
     * Update country
     */
    public function update(int $id, array $data): ?Country;

    /**
     * Delete country
     */
    public function delete(int $id): bool;
}
