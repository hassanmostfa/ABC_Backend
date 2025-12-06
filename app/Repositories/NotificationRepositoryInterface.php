<?php

namespace App\Repositories;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface NotificationRepositoryInterface
{
    /**
     * Get all notifications with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get all notifications for a specific admin
     */
    public function getByAdminId(int $adminId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get all notifications for a specific customer
     */
    public function getByCustomerId(int $customerId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get unread notifications for a specific admin
     */
    public function getUnreadByAdminId(int $adminId): Collection;

    /**
     * Get unread notifications for a specific customer
     */
    public function getUnreadByCustomerId(int $customerId): Collection;

    /**
     * Get unread count for a specific admin
     */
    public function getUnreadCountByAdminId(int $adminId): int;

    /**
     * Get unread count for a specific customer
     */
    public function getUnreadCountByCustomerId(int $customerId): int;

    /**
     * Get all notifications
     */
    public function getAll(): Collection;

    /**
     * Get notification by ID
     */
    public function findById(int $id): ?Notification;

    /**
     * Create a new notification
     */
    public function create(array $data): Notification;

    /**
     * Update notification
     */
    public function update(int $id, array $data): ?Notification;

    /**
     * Delete notification
     */
    public function delete(int $id): bool;

    /**
     * Mark notification as read
     */
    public function markAsRead(int $id): bool;

    /**
     * Mark all notifications as read for an admin
     */
    public function markAllAsReadForAdmin(int $adminId): int;

    /**
     * Mark all notifications as read for a customer
     */
    public function markAllAsReadForCustomer(int $customerId): int;
}

