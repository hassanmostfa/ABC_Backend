<?php

namespace App\Repositories;

use App\Models\Charity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CharityRepository implements CharityRepositoryInterface
{
    protected $model;

    public function __construct(Charity $charity)
    {
        $this->model = $charity;
    }

    /**
     * Get all charities with pagination
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with(['offers', 'country', 'governorate', 'area']);

        // Apply search filter if provided
        if (isset($filters['search']) && !empty(trim($filters['search']))) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name_en', 'like', "%{$search}%")
                  ->orWhere('name_ar', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhereHas('country', function ($countryQuery) use ($search) {
                      $countryQuery->where('name_en', 'like', "%{$search}%")
                                   ->orWhere('name_ar', 'like', "%{$search}%");
                  })
                  ->orWhereHas('governorate', function ($governorateQuery) use ($search) {
                      $governorateQuery->where('name_en', 'like', "%{$search}%")
                                       ->orWhere('name_ar', 'like', "%{$search}%");
                  })
                  ->orWhereHas('area', function ($areaQuery) use ($search) {
                      $areaQuery->where('name_en', 'like', "%{$search}%")
                                ->orWhere('name_ar', 'like', "%{$search}%");
                  });
            });
        }

        // Default sorting by created_at desc
        $query->orderBy('created_at', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get all charities
     */
    public function getAll(): Collection
    {
        return $this->model->with(['offers', 'country', 'governorate', 'area'])->get();
    }

    /**
     * Get all charities with filters (no pagination)
     */
    public function getAllWithFilters(array $filters = []): Collection
    {
        $query = $this->model->with(['offers', 'country', 'governorate', 'area']);

        // Apply search filter if provided
        if (isset($filters['search']) && !empty(trim($filters['search']))) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name_en', 'like', "%{$search}%")
                  ->orWhere('name_ar', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhereHas('country', function ($countryQuery) use ($search) {
                      $countryQuery->where('name_en', 'like', "%{$search}%")
                                   ->orWhere('name_ar', 'like', "%{$search}%");
                  })
                  ->orWhereHas('governorate', function ($governorateQuery) use ($search) {
                      $governorateQuery->where('name_en', 'like', "%{$search}%")
                                       ->orWhere('name_ar', 'like', "%{$search}%");
                  })
                  ->orWhereHas('area', function ($areaQuery) use ($search) {
                      $areaQuery->where('name_en', 'like', "%{$search}%")
                                ->orWhere('name_ar', 'like', "%{$search}%");
                  });
            });
        }

        // Default sorting by created_at desc
        $query->orderBy('created_at', 'desc');

        return $query->get();
    }

    /**
     * Get charity by ID
     */
    public function findById(int $id): ?Charity
    {
        return $this->model->with(['offers', 'country', 'governorate', 'area'])->find($id);
    }

    /**
     * Create a new charity
     */
    public function create(array $data): Charity
    {
        return $this->model->create($data);
    }

    /**
     * Update charity
     */
    public function update(int $id, array $data): ?Charity
    {
        $charity = $this->model->find($id);
        
        if (!$charity) {
            return null;
        }

        $charity->update($data);
        return $charity->load(['offers', 'country', 'governorate', 'area']);
    }

    /**
     * Delete charity
     */
    public function delete(int $id): bool
    {
        $charity = $this->model->find($id);
        
        if (!$charity) {
            return false;
        }

        return $charity->delete();
    }
}
