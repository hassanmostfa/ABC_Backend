<?php

namespace App\Repositories;

use App\Models\Career;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CareerRepository implements CareerRepositoryInterface
{
    protected $model;

    public function __construct(Career $career)
    {
        $this->model = $career;
    }

    /**
     * Get all career applications with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Search functionality
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhere('applying_position', 'LIKE', "%{$search}%");
            });
        }

        // Filter by position
        if (isset($filters['position']) && !empty($filters['position'])) {
            $query->where('applying_position', 'LIKE', "%{$filters['position']}%");
        }

        // Default sorting by created_at desc
        $query->orderBy('created_at', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get all career applications
     */
    public function getAll(): Collection
    {
        return $this->model->all();
    }

    /**
     * Get career application by ID
     */
    public function findById(int $id): ?Career
    {
        return $this->model->find($id);
    }

    /**
     * Create a new career application
     */
    public function create(array $data): Career
    {
        return $this->model->create($data);
    }

    /**
     * Update career application
     */
    public function update(int $id, array $data): ?Career
    {
        $career = $this->model->find($id);
        
        if (!$career) {
            return null;
        }

        $career->update($data);
        return $career;
    }

    /**
     * Delete career application
     */
    public function delete(int $id): bool
    {
        $career = $this->model->find($id);
        
        if (!$career) {
            return false;
        }

        return $career->delete();
    }
}
