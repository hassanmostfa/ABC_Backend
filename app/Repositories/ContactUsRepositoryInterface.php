<?php

namespace App\Repositories;

use App\Models\ContactUs;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ContactUsRepositoryInterface
{
    /**
     * Get all contact messages with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get all contact messages
     */
    public function getAll(): Collection;

    /**
     * Get contact message by ID
     */
    public function findById(int $id): ?ContactUs;

    /**
     * Create a new contact message
     */
    public function create(array $data): ContactUs;


    /**
     * Delete contact message
     */
    public function delete(int $id): bool;


    /**
     * Mark message as read
     */
    public function markAsRead(int $id): bool;

}
