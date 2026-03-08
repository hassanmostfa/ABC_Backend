<?php

use App\Models\Admin;
use App\Models\Customer;
use App\Repositories\NotificationRepositoryInterface;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

if (!function_exists('sendNotification')) {
    /**
     * Send in-app notification and (for customers) push via Firebase.
     *
     * @param  array<string, mixed>|null  $data  Optional payload (values stringified for FCM)
     * @return \App\Models\Notification|true|null
     */
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
    ) {
        $repository = App::make(NotificationRepositoryInterface::class);
        $sendToAdmins = (bool) config('notifications.send_to_admins', false);
        $sendToFirebase = (bool) config('notifications.send_to_firebase', true);

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

        // If customerId is provided, send to that customer (in-app + optional Firebase push)
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

            if ($sendToFirebase) {
                sendNotificationToFirebase($customerId, $resolvedTitleEn, $resolvedMessageEn, $type, $data, $notification->id);
            }

            return $notification;
        }

        return null;
    }
}

if (!function_exists('sendNotificationToFirebase')) {
    /**
     * Send FCM push to all device tokens of a customer.
     *
     * @param  array<string, mixed>|null  $data  Extra payload (values stringified for FCM)
     */
    function sendNotificationToFirebase(
        int $customerId,
        string $title,
        string $body,
        string $type = 'info',
        ?array $data = null,
        ?int $notificationId = null
    ): void {
        try {
            $tokens = \App\Models\DeviceToken::query()
                ->where('customer_id', $customerId)
                ->pluck('token')
                ->filter()
                ->values()
                ->all();

            if (empty($tokens)) {
                return;
            }

            $payload = [
                'type' => $type,
                'notification_id' => (string) ($notificationId ?? ''),
            ];
            if (! empty($data)) {
                foreach ($data as $k => $v) {
                    $payload[$k] = is_scalar($v) ? (string) $v : json_encode($v);
                }
            }

            $firebase = App::make(FirebaseService::class);
            $firebase->sendNotificationToMultiple($tokens, $title, $body, $payload);
        } catch (\Throwable $e) {
            Log::warning('Firebase push failed', [
                'customer_id' => $customerId,
                'title' => $title,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

