<?php

namespace App\Repositories;

use App\Models\Area;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class AreaRepository implements AreaRepositoryInterface
{
    protected $model;

    public function __construct(Area $area)
    {
        $this->model = $area;
    }

    /**
     * Get all areas with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with(['governorate.country']);

        // Apply search filter if provided
        if (isset($filters['search']) && !empty(trim($filters['search']))) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name_en', 'like', "%{$search}%")
                  ->orWhere('name_ar', 'like', "%{$search}%");
            });
        }

        // Apply governorate filter if provided
        if (isset($filters['governorate_id']) && !empty($filters['governorate_id'])) {
            $query->where('governorate_id', $filters['governorate_id']);
        }

        // Apply country filter if provided
        if (isset($filters['country_id']) && !empty($filters['country_id'])) {
            $query->whereHas('governorate', function ($q) use ($filters) {
                $q->where('country_id', $filters['country_id']);
            });
        }

        // Default sorting by name_en asc
        $query->orderBy('name_en', 'asc');

        return $query->paginate($perPage);
    }

    /**
     * Get all areas
     */
    public function getAll(): Collection
    {
        return $this->model->with(['governorate.country'])->orderBy('name_en', 'asc')->get();
    }

    /**
     * Get area by ID
     */
    public function findById(int $id): ?Area
    {
        return $this->model->with(['governorate.country'])->find($id);
    }

    /**
     * Get areas by governorate ID
     */
    public function getByGovernorate(int $governorateId): Collection
    {
        return $this->model->where('governorate_id', $governorateId)
                          ->orderBy('name_en', 'asc')
                          ->get();
    }

    /**
     * Get areas by country ID
     */
    public function getByCountry(int $countryId): Collection
    {
        return $this->model->whereHas('governorate', function ($q) use ($countryId) {
            $q->where('country_id', $countryId);
        })->orderBy('name_en', 'asc')->get();
    }

    /**
     * Create a new area
     */
    public function create(array $data): Area
    {
        return $this->model->create($data);
    }

    /**
     * Update area
     */
    public function update(int $id, array $data): ?Area
    {
        $area = $this->model->find($id);
        
        if (!$area) {
            return null;
        }

        $area->update($data);
        return $area->load(['governorate.country']);
    }

    /**
     * Delete area
     */
    public function delete(int $id): bool
    {
        $area = $this->model->find($id);
        
        if (!$area) {
            return false;
        }

        return $area->delete();
    }
}
