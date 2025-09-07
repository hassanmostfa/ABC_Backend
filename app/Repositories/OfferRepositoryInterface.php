<?php

namespace App\Repositories;

use App\Models\Offer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface OfferRepositoryInterface
{
    /**
     * Get all offers with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get all offers
     */
    public function getAll(): Collection;

    /**
     * Get offer by ID
     */
    public function findById(int $id): ?Offer;

    /**
     * Create a new offer
     */
    public function create(array $data): Offer;

    /**
     * Update offer
     */
    public function update(int $id, array $data): ?Offer;

    /**
     * Delete offer
     */
    public function delete(int $id): bool;
}
