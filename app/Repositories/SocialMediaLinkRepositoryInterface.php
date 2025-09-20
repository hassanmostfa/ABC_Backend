<?php

namespace App\Repositories;

use App\Models\SocialMediaLink;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface SocialMediaLinkRepositoryInterface
{
    /**
     * Get all social media links with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get all social media links
     */
    public function getAll(): Collection;

    /**
     * Get social media link by ID
     */
    public function findById(int $id): ?SocialMediaLink;

    /**
     * Create a new social media link
     */
    public function create(array $data): SocialMediaLink;

    /**
     * Update social media link
     */
    public function update(int $id, array $data): ?SocialMediaLink;

    /**
     * Delete social media link
     */
    public function delete(int $id): bool;

    /**
     * Get active social media links only
     */
    public function getActive(): Collection;

    /**
     * Get inactive social media links only
     */
    public function getInactive(): Collection;
}
