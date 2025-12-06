<?php

namespace App\Repositories;

use App\Models\TeamMember;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface TeamMemberRepositoryInterface
{
    /**
     * Get all team members with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get all team members
     */
    public function getAll(): Collection;

    /**
     * Get team member by ID
     */
    public function findById(int $id): ?TeamMember;

    /**
     * Create a new team member
     */
    public function create(array $data): TeamMember;

    /**
     * Update team member
     */
    public function update(int $id, array $data): ?TeamMember;

    /**
     * Delete team member
     */
    public function delete(int $id): bool;
}

