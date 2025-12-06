<?php

namespace App\Repositories;

use App\Models\Slider;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface SliderRepositoryInterface
{
    /**
     * Get all sliders with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get all sliders
     */
    public function getAll(): Collection;

    /**
     * Get published sliders only
     */
    public function getPublished(): Collection;

    /**
     * Get slider by ID
     */
    public function findById(int $id): ?Slider;

    /**
     * Create a new slider
     */
    public function create(array $data): Slider;

    /**
     * Update slider
     */
    public function update(int $id, array $data): ?Slider;

    /**
     * Delete slider
     */
    public function delete(int $id): bool;
}

