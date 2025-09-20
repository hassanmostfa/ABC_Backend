<?php

namespace App\Repositories;

use App\Models\Career;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface CareerRepositoryInterface
{
    /**
     * Get all career applications with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get all career applications
     */
    public function getAll(): Collection;

    /**
     * Get career application by ID
     */
    public function findById(int $id): ?Career;

    /**
     * Create a new career application
     */
    public function create(array $data): Career;

    /**
     * Update career application
     */
    public function update(int $id, array $data): ?Career;

    /**
     * Delete career application
     */
    public function delete(int $id): bool;
}
