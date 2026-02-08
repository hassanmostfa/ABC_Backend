<?php

namespace App\Repositories;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface FaqRepositoryInterface
{
    /**
     * Get all FAQs with pagination, search and filters.
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get all FAQs.
     */
    public function getAll(): Collection;

    /**
     * Get FAQ by ID.
     */
    public function findById(int $id): ?Faq;

    /**
     * Create a new FAQ.
     */
    public function create(array $data): Faq;

    /**
     * Update FAQ.
     */
    public function update(int $id, array $data): ?Faq;

    /**
     * Delete FAQ.
     */
    public function delete(int $id): bool;
}
