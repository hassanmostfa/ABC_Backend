<?php

namespace App\Repositories;

use App\Models\TeamMember;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class TeamMemberRepository implements TeamMemberRepositoryInterface
{
    protected $model;

    public function __construct(TeamMember $teamMember)
    {
        $this->model = $teamMember;
    }

    /**
     * Get all team members with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Search functionality
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('job_title', 'LIKE', "%{$search}%")
                  ->orWhere('level', 'LIKE', "%{$search}%");
            });
        }

        // Filter by level
        if (isset($filters['level']) && !empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }

        // Filter by job title
        if (isset($filters['job_title']) && !empty($filters['job_title'])) {
            $query->where('job_title', 'LIKE', "%{$filters['job_title']}%");
        }

        // Default sorting by created_at desc
        $query->orderBy('level', 'asc');

        return $query->paginate($perPage);
    }

    /**
     * Get all team members
     */
    public function getAll(): Collection
    {
        return $this->model->orderBy('level', 'asc')->get();
    }

    /**
     * Get team member by ID
     */
    public function findById(int $id): ?TeamMember
    {
        return $this->model->find($id);
    }

    /**
     * Create a new team member
     */
    public function create(array $data): TeamMember
    {
        return $this->model->create($data);
    }

    /**
     * Update team member
     */
    public function update(int $id, array $data): ?TeamMember
    {
        $teamMember = $this->model->find($id);
        
        if (!$teamMember) {
            return null;
        }

        $teamMember->update($data);
        return $teamMember;
    }

    /**
     * Delete team member
     */
    public function delete(int $id): bool
    {
        $teamMember = $this->model->find($id);
        
        if (!$teamMember) {
            return false;
        }

        return $teamMember->delete();
    }
}

