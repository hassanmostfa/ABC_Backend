<?php

namespace App\Repositories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CategoryRepository implements CategoryRepositoryInterface
{
    protected $model;

    public function __construct(Category $category)
    {
        $this->model = $category;
    }

    /**
     * Get all categories with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Search functionality
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name_en', 'LIKE', "%{$search}%")
                  ->orWhere('name_ar', 'LIKE', "%{$search}%");
            });
        }

        // Filter by status
        if (isset($filters['status']) && $filters['status'] !== '') {
            if ($filters['status'] === 'active') {
                $query->where('is_active', true);
            } elseif ($filters['status'] === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Default sorting by created_at desc
        $query->orderBy('created_at', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get all categories
     */
    public function getAll(): Collection
    {
        return $this->model->all();
    }

    /**
     * Get category by ID
     */
    public function findById(int $id): ?Category
    {
        return $this->model->find($id);
    }

    /**
     * Create a new category
     */
    public function create(array $data): Category
    {
        return $this->model->create($data);
    }

    /**
     * Update category
     */
    public function update(int $id, array $data): ?Category
    {
        $category = $this->model->find($id);
        
        if (!$category) {
            return null;
        }

        $category->update($data);
        return $category;
    }

    /**
     * Delete category
     */
    public function delete(int $id): bool
    {
        $category = $this->model->find($id);
        
        if (!$category) {
            return false;
        }

        return $category->delete();
    }

    /**
     * Get active categories only
     */
    public function getActive(): Collection
    {
        return $this->model->where('is_active', true)->get();
    }

    /**
     * Get inactive categories only
     */
    public function getInactive(): Collection
    {
        return $this->model->where('is_active', false)->get();
    }
}
