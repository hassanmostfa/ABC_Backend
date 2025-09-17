<?php

namespace App\Repositories;

use App\Models\ContactUs;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ContactUsRepository implements ContactUsRepositoryInterface
{
    protected $model;

    public function __construct(ContactUs $contactUs)
    {
        $this->model = $contactUs;
    }

    /**
     * Get all contact messages with pagination, search and filters
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
                  ->orWhere('message', 'LIKE', "%{$search}%");
            });
        }

        // Filter by read status
        if (isset($filters['status']) && $filters['status'] !== '') {
            if ($filters['status'] === 'read') {
                $query->where('is_read', true);
            } elseif ($filters['status'] === 'unread') {
                $query->where('is_read', false);
            }
        }

        // Sort functionality
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        
        // Validate sort fields
        $allowedSortFields = ['name', 'email', 'is_read', 'created_at', 'updated_at'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'created_at';
        }
        
        $allowedSortOrders = ['asc', 'desc'];
        if (!in_array($sortOrder, $allowedSortOrders)) {
            $sortOrder = 'desc';
        }

        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get all contact messages
     */
    public function getAll(): Collection
    {
        return $this->model->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get contact message by ID
     */
    public function findById(int $id): ?ContactUs
    {
        return $this->model->find($id);
    }

    /**
     * Create a new contact message
     */
    public function create(array $data): ContactUs
    {
        return $this->model->create($data);
    }


    /**
     * Delete contact message
     */
    public function delete(int $id): bool
    {
        $contactMessage = $this->findById($id);
        
        if ($contactMessage) {
            return $contactMessage->delete();
        }

        return false;
    }


    /**
     * Mark message as read
     */
    public function markAsRead(int $id): bool
    {
        $contactMessage = $this->findById($id);
        
        if ($contactMessage) {
            return $contactMessage->update(['is_read' => true]);
        }

        return false;
    }
}
