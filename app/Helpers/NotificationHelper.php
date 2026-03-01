<?php

use App\Models\Admin;
use App\Models\Customer;
use App\Repositories\NotificationRepositoryInterface;
use Illuminate\Support\Facades\App;

if (!function_exists('sendNotification')) {
    function sendNotification(
        ?int $adminId = null,
        ?int $customerId = null,
        string $title,
        string $message,
        string $type = 'info',
        ?array $data = null,
        ?string $titleAr = null,
        ?string $messageAr = null,
        ?string $titleEn = null,
        ?string $messageEn = null
    )
    {
        $repository = App::make(NotificationRepositoryInterface::class);
        $sendToAdmins = (bool) config('notifications.send_to_admins', false);

        $resolvedTitleEn = $titleEn ?? $title;
        $resolvedMessageEn = $messageEn ?? $message;
        $resolvedTitleAr = $titleAr ?? $resolvedTitleEn;
        $resolvedMessageAr = $messageAr ?? $resolvedMessageEn;
        
        // If both are null, send to all active admins
        if ($adminId === null && $customerId === null) {
            if (!$sendToAdmins) {
                return true;
            }
            $admins = Admin::query()->where('is_active', true)->pluck('id');
            foreach ($admins as $id) {
                $notification = $repository->create([
                    'notifiable_type' => Admin::class,
                    'notifiable_id' => $id,
                    'type' => $type,
                    'data' => $data,
                ]);
                $notification->translations()->createMany([
                    [
                        'locale' => 'en',
                        'title' => $resolvedTitleEn,
                        'message' => $resolvedMessageEn,
                    ],
                    [
                        'locale' => 'ar',
                        'title' => $resolvedTitleAr,
                        'message' => $resolvedMessageAr,
                    ],
                ]);
            }
            return true;
        }

        // If adminId is provided, send to that admin
        if ($adminId !== null) {
            if (!$sendToAdmins) {
                return true;
            }
            $notification = $repository->create([
                'notifiable_type' => Admin::class,
                'notifiable_id' => $adminId,
                'type' => $type,
                'data' => $data,
            ]);
            $notification->translations()->createMany([
                [
                    'locale' => 'en',
                    'title' => $resolvedTitleEn,
                    'message' => $resolvedMessageEn,
                ],
                [
                    'locale' => 'ar',
                    'title' => $resolvedTitleAr,
                    'message' => $resolvedMessageAr,
                ],
            ]);
            return $notification;
        }

        // If customerId is provided, send to that customer
        if ($customerId !== null) {
            $notification = $repository->create([
                'notifiable_type' => Customer::class,
                'notifiable_id' => $customerId,
                'type' => $type,
                'data' => $data,
            ]);
            $notification->translations()->createMany([
                [
                    'locale' => 'en',
                    'title' => $resolvedTitleEn,
                    'message' => $resolvedMessageEn,
                ],
                [
                    'locale' => 'ar',
                    'title' => $resolvedTitleAr,
                    'message' => $resolvedMessageAr,
                ],
            ]);
            return $notification;
        }
    }
}

