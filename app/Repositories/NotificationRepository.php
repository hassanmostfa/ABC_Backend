<?php

namespace App\Repositories;

use App\Models\Notification;
use App\Models\Admin;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationRepository implements NotificationRepositoryInterface
{
    protected $model;

    public function __construct(Notification $notification)
    {
        $this->model = $notification;
    }

    /**
     * Get all notifications with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Filter by notifiable type and id (polymorphic)
        if (isset($filters['notifiable_type']) && $filters['notifiable_type'] !== '') {
            $query->where('notifiable_type', $filters['notifiable_type']);
        }

        if (isset($filters['notifiable_id']) && $filters['notifiable_id'] !== '') {
            $query->where('notifiable_id', $filters['notifiable_id']);
        }

        // Filter by type
        if (isset($filters['type']) && $filters['type'] !== '') {
            $query->where('type', $filters['type']);
        }

        // Filter by read status
        if (isset($filters['is_read']) && $filters['is_read'] !== '') {
            $query->where('is_read', $filters['is_read'] === 'true' || $filters['is_read'] === true);
        }

        // Search functionality
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('message', 'LIKE', "%{$search}%");
            });
        }

        // Default sorting by created_at desc
        $query->orderBy('created_at', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get all notifications for a specific admin
     */
    public function getByAdminId(int $adminId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $filters['notifiable_type'] = Admin::class;
        $filters['notifiable_id'] = $adminId;
        return $this->getAllPaginated($filters, $perPage);
    }

    /**
     * Get all notifications for a specific customer
     */
    public function getByCustomerId(int $customerId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $filters['notifiable_type'] = Customer::class;
        $filters['notifiable_id'] = $customerId;
        return $this->getAllPaginated($filters, $perPage);
    }

    /**
     * Get unread notifications for a specific admin
     */
    public function getUnreadByAdminId(int $adminId): Collection
    {
        return $this->model->where('notifiable_type', Admin::class)
            ->where('notifiable_id', $adminId)
            ->where('is_read', false)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get unread notifications for a specific customer
     */
    public function getUnreadByCustomerId(int $customerId): Collection
    {
        return $this->model->where('notifiable_type', Customer::class)
            ->where('notifiable_id', $customerId)
            ->where('is_read', false)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get unread count for a specific admin
     */
    public function getUnreadCountByAdminId(int $adminId): int
    {
        return $this->model->where('notifiable_type', Admin::class)
            ->where('notifiable_id', $adminId)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Get unread count for a specific customer
     */
    public function getUnreadCountByCustomerId(int $customerId): int
    {
        return $this->model->where('notifiable_type', Customer::class)
            ->where('notifiable_id', $customerId)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Get all notifications
     */
    public function getAll(): Collection
    {
        return $this->model->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get notification by ID
     */
    public function findById(int $id): ?Notification
    {
        return $this->model->find($id);
    }

    /**
     * Create a new notification
     */
    public function create(array $data): Notification
    {
        return $this->model->create($data);
    }

    /**
     * Update notification
     */
    public function update(int $id, array $data): ?Notification
    {
        $notification = $this->model->find($id);
        
        if (!$notification) {
            return null;
        }

        $notification->update($data);
        return $notification;
    }

    /**
     * Delete notification
     */
    public function delete(int $id): bool
    {
        $notification = $this->model->find($id);
        
        if (!$notification) {
            return false;
        }

        return $notification->delete();
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $id): bool
    {
        $notification = $this->model->find($id);
        
        if (!$notification) {
            return false;
        }

        return $notification->markAsRead();
    }

    /**
     * Mark all notifications as read for an admin
     */
    public function markAllAsReadForAdmin(int $adminId): int
    {
        return $this->model->where('notifiable_type', Admin::class)
            ->where('notifiable_id', $adminId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Mark all notifications as read for a customer
     */
    public function markAllAsReadForCustomer(int $customerId): int
    {
        return $this->model->where('notifiable_type', Customer::class)
            ->where('notifiable_id', $customerId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }
}

