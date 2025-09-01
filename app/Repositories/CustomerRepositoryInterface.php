<?php

namespace App\Repositories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface CustomerRepositoryInterface
{
    /**
     * Get all customers with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get all customers
     */
    public function getAll(): Collection;

    /**
     * Get customer by ID
     */
    public function findById(int $id): ?Customer;

    /**
     * Create a new customer
     */
    public function create(array $data): Customer;

    /**
     * Update customer
     */
    public function update(int $id, array $data): ?Customer;

    /**
     * Delete customer
     */
    public function delete(int $id): bool;

    /**
     * Get active customers only
     */
    public function getActive(): Collection;

    /**
     * Get inactive customers only
     */
    public function getInactive(): Collection;
}
