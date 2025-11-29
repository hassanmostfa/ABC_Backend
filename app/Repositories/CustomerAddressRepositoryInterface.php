<?php

namespace App\Repositories;

use App\Models\CustomerAddress;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface CustomerAddressRepositoryInterface
{
    /**
     * Get all customer addresses with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get all customer addresses
     */
    public function getAll(): Collection;

    /**
     * Get customer address by ID
     */
    public function findById(int $id): ?CustomerAddress;

    /**
     * Create a new customer address
     */
    public function create(array $data): CustomerAddress;

    /**
     * Update customer address
     */
    public function update(int $id, array $data): ?CustomerAddress;

    /**
     * Delete customer address
     */
    public function delete(int $id): bool;

    /**
     * Get addresses by customer ID
     */
    public function getByCustomer(int $customerId): Collection;
}

