<?php

namespace App\Traits;

use App\Models\Customer;
use App\Models\Notification;

trait CustomerUnreadNotificationsCountTrait
{
    /**
     * Get unread notifications count for a customer ID.
     */
    protected function getUnreadNotificationsCount(?int $customerId): int
    {
        if (!$customerId) {
            return 0;
        }

        return Notification::query()
            ->where('notifiable_type', Customer::class)
            ->where('notifiable_id', $customerId)
            ->where('is_read', false)
            ->count();
    }
}
