<?php

namespace App\Repositories\Services;

use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ServiceRepositoryInterface
{
    /**
     * Get all services with pagination, search and filters.
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get all services.
     */
    public function getAll(): Collection;

    /**
     * Get active services without pagination.
     */
    public function getActive(): Collection;

    /**
     * Get service by ID.
     */
    public function findById(int $id): ?Service;

    /**
     * Create a new service.
     */
    public function create(array $data): Service;

    /**
     * Update service.
     */
    public function update(int $id, array $data): ?Service;

    /**
     * Delete service.
     */
    public function delete(int $id): bool;
}
