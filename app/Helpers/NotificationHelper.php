<?php

use App\Models\Admin;
use App\Models\Customer;
use App\Repositories\NotificationRepositoryInterface;
use Illuminate\Support\Facades\App;

if (!function_exists('sendNotification')) {
    function sendNotification(?int $adminId = null, ?int $customerId = null, string $title, string $message, string $type = 'info', ?array $data = null)
    {
        $repository = App::make(NotificationRepositoryInterface::class);
        
        // If both are null, send to all admins
        if ($adminId === null && $customerId === null) {
                return response()->json([
                'message' => 'Admin or customer ID is required',
                'status' => 'error',
                ], 400);
        }

        // If adminId is provided, send to that admin
        if ($adminId !== null) {
            $notificationData = [
                'notifiable_type' => Admin::class,
                'notifiable_id' => $adminId,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'data' => $data,
            ];
            return $repository->create($notificationData);
        }

        // If customerId is provided, send to that customer
        if ($customerId !== null) {
            $notificationData = [
                'notifiable_type' => Customer::class,
                'notifiable_id' => $customerId,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'data' => $data,
            ];
            return $repository->create($notificationData);
        }
    }
}

