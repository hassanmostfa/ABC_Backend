<?php

namespace App\Repositories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface OrderRepositoryInterface
{
    /**
     * Get all orders with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get all orders
     */
    public function getAll(): Collection;

    /**
     * Get order by ID
     */
    public function findById(int $id): ?Order;

    /**
     * Get order by order number
     */
    public function findByOrderNumber(string $orderNumber): ?Order;

    /**
     * Create a new order
     */
    public function create(array $data): Order;

    /**
     * Update order
     */
    public function update(int $id, array $data): ?Order;

    /**
     * Delete order
     */
    public function delete(int $id): bool;

    /**
     * Get orders by customer ID
     */
    public function getByCustomer(int $customerId): Collection;

    /**
     * Get orders by charity ID
     */
    public function getByCharity(int $charityId): Collection;

    /**
     * Get orders by status
     */
    public function getByStatus(string $status): Collection;
}

