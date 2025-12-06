<?php

namespace App\Repositories;

use App\Models\Slider;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class SliderRepository implements SliderRepositoryInterface
{
    protected $model;

    public function __construct(Slider $slider)
    {
        $this->model = $slider;
    }

    /**
     * Get all sliders with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Filter by published status
        if (isset($filters['is_published']) && $filters['is_published'] !== '') {
            $query->where('is_published', $filters['is_published'] === 'true' || $filters['is_published'] === true);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get all sliders
     */
    public function getAll(): Collection
    {
        return $this->model->get();
    }

    /**
     * Get published sliders only
     */
    public function getPublished(): Collection
    {
        return $this->model->where('is_published', true)
            ->get();
    }

    /**
     * Get slider by ID
     */
    public function findById(int $id): ?Slider
    {
        return $this->model->find($id);
    }

    /**
     * Create a new slider
     */
    public function create(array $data): Slider
    {
        return $this->model->create($data);
    }

    /**
     * Update slider
     */
    public function update(int $id, array $data): ?Slider
    {
        $slider = $this->model->find($id);
        
        if (!$slider) {
            return null;
        }

        $slider->update($data);
        return $slider;
    }

    /**
     * Delete slider
     */
    public function delete(int $id): bool
    {
        $slider = $this->model->find($id);
        
        if (!$slider) {
            return false;
        }

        return $slider->delete();
    }
}

