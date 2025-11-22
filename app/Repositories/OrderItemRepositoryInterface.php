<?php

namespace App\Repositories;

use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface OrderItemRepositoryInterface
{
    /**
     * Get all order items with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get all order items
     */
    public function getAll(): Collection;

    /**
     * Get order item by ID
     */
    public function findById(int $id): ?OrderItem;

    /**
     * Create a new order item
     */
    public function create(array $data): OrderItem;

    /**
     * Update order item
     */
    public function update(int $id, array $data): ?OrderItem;

    /**
     * Delete order item
     */
    public function delete(int $id): bool;

    /**
     * Get order items by order ID
     */
    public function getByOrder(int $orderId): Collection;

    /**
     * Get order items by product ID
     */
    public function getByProduct(int $productId): Collection;

    /**
     * Get order items that are offers
     */
    public function getOfferItems(): Collection;
}

