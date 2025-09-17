<?php

namespace App\Repositories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CountryRepository implements CountryRepositoryInterface
{
    protected $model;

    public function __construct(Country $country)
    {
        $this->model = $country;
    }

    /**
     * Get all countries with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->newQuery();

        // Apply search filter if provided
        if (isset($filters['search']) && !empty(trim($filters['search']))) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name_en', 'like', "%{$search}%")
                  ->orWhere('name_ar', 'like', "%{$search}%");
            });
        }

        // Default sorting by name_en asc
        $query->orderBy('name_en', 'asc');

        return $query->paginate($perPage);
    }

    /**
     * Get all countries
     */
    public function getAll(): Collection
    {
        return $this->model->orderBy('name_en', 'asc')->get();
    }

    /**
     * Get country by ID
     */
    public function findById(int $id): ?Country
    {
        return $this->model->find($id);
    }

    /**
     * Create a new country
     */
    public function create(array $data): Country
    {
        return $this->model->create($data);
    }

    /**
     * Update country
     */
    public function update(int $id, array $data): ?Country
    {
        $country = $this->model->find($id);
        
        if (!$country) {
            return null;
        }

        $country->update($data);
        return $country;
    }

    /**
     * Delete country
     */
    public function delete(int $id): bool
    {
        $country = $this->model->find($id);
        
        if (!$country) {
            return false;
        }

        return $country->delete();
    }
}
