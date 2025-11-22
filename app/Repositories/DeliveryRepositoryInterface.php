<?php

namespace App\Repositories;

use App\Models\Delivery;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface DeliveryRepositoryInterface
{
    /**
     * Get all deliveries with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get all deliveries
     */
    public function getAll(): Collection;

    /**
     * Get delivery by ID
     */
    public function findById(int $id): ?Delivery;

    /**
     * Create a new delivery
     */
    public function create(array $data): Delivery;

    /**
     * Update delivery
     */
    public function update(int $id, array $data): ?Delivery;

    /**
     * Delete delivery
     */
    public function delete(int $id): bool;

    /**
     * Get delivery by order ID
     */
    public function getByOrder(int $orderId): ?Delivery;

    /**
     * Get deliveries by status
     */
    public function getByStatus(string $status): Collection;

    /**
     * Get deliveries by payment method
     */
    public function getByPaymentMethod(string $paymentMethod): Collection;

    /**
     * Get pending deliveries
     */
    public function getPending(): Collection;

    /**
     * Get in-transit deliveries
     */
    public function getInTransit(): Collection;

    /**
     * Get delivered deliveries
     */
    public function getDelivered(): Collection;
}

