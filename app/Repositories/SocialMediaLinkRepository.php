<?php

namespace App\Repositories;

use App\Models\SocialMediaLink;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class SocialMediaLinkRepository implements SocialMediaLinkRepositoryInterface
{
    protected $model;

    public function __construct(SocialMediaLink $socialMediaLink)
    {
        $this->model = $socialMediaLink;
    }

    /**
     * Get all social media links with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Search functionality
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('icon', 'LIKE', "%{$search}%")
                  ->orWhere('title_en', 'LIKE', "%{$search}%")
                  ->orWhere('title_ar', 'LIKE', "%{$search}%")
                  ->orWhere('url', 'LIKE', "%{$search}%");
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
     * Get all social media links
     */
    public function getAll(): Collection
    {
        return $this->model->all();
    }

    /**
     * Get social media link by ID
     */
    public function findById(int $id): ?SocialMediaLink
    {
        return $this->model->find($id);
    }

    /**
     * Create a new social media link
     */
    public function create(array $data): SocialMediaLink
    {
        return $this->model->create($data);
    }

    /**
     * Update social media link
     */
    public function update(int $id, array $data): ?SocialMediaLink
    {
        $socialMediaLink = $this->model->find($id);
        
        if (!$socialMediaLink) {
            return null;
        }

        $socialMediaLink->update($data);
        return $socialMediaLink;
    }

    /**
     * Delete social media link
     */
    public function delete(int $id): bool
    {
        $socialMediaLink = $this->model->find($id);
        
        if (!$socialMediaLink) {
            return false;
        }

        return $socialMediaLink->delete();
    }

    /**
     * Get active social media links only
     */
    public function getActive(): Collection
    {
        return $this->model->where('is_active', true)->get();
    }

    /**
     * Get inactive social media links only
     */
    public function getInactive(): Collection
    {
        return $this->model->where('is_active', false)->get();
    }
}
