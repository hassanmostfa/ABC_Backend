<?php

namespace App\Repositories;

use App\Models\Governorate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class GovernorateRepository implements GovernorateRepositoryInterface
{
    protected $model;

    public function __construct(Governorate $governorate)
    {
        $this->model = $governorate;
    }

    /**
     * Get all governorates with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with(['country']);

        // Apply search filter if provided
        if (isset($filters['search']) && !empty(trim($filters['search']))) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name_en', 'like', "%{$search}%")
                  ->orWhere('name_ar', 'like', "%{$search}%");
            });
        }

        // Apply country filter if provided
        if (isset($filters['country_id']) && !empty($filters['country_id'])) {
            $query->where('country_id', $filters['country_id']);
        }

        // Default sorting by name_en asc
        $query->orderBy('name_en', 'asc');

        return $query->paginate($perPage);
    }

    /**
     * Get all governorates
     */
    public function getAll(): Collection
    {
        return $this->model->with(['country'])->orderBy('name_en', 'asc')->get();
    }

    /**
     * Get governorate by ID
     */
    public function findById(int $id): ?Governorate
    {
        return $this->model->with(['country'])->find($id);
    }

    /**
     * Get governorates by country ID
     */
    public function getByCountry(int $countryId): Collection
    {
        return $this->model->where('country_id', $countryId)
                          ->orderBy('name_en', 'asc')
                          ->get();
    }

    /**
     * Create a new governorate
     */
    public function create(array $data): Governorate
    {
        return $this->model->create($data);
    }

    /**
     * Update governorate
     */
    public function update(int $id, array $data): ?Governorate
    {
        $governorate = $this->model->find($id);
        
        if (!$governorate) {
            return null;
        }

        $governorate->update($data);
        return $governorate->load(['country']);
    }

    /**
     * Delete governorate
     */
    public function delete(int $id): bool
    {
        $governorate = $this->model->find($id);
        
        if (!$governorate) {
            return false;
        }

        return $governorate->delete();
    }
}
