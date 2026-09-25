<?php

namespace App\Services\Notification;

use App\Jobs\DispatchGeneralNotificationJob;
use App\Jobs\SendGeneralNotificationChunkJob;
use App\Models\Customer;
use App\Models\DeviceToken;
use App\Models\GeneralNotification;
use App\Models\Notification;
use App\Models\NotificationTranslation;
use App\Repositories\GeneralNotifications\GeneralNotificationRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GeneralNotificationService
{
    public const CHUNK_SIZE = 200;

    public function __construct(
        protected GeneralNotificationRepositoryInterface $generalNotificationRepository
    ) {}

    /**
     * Store the broadcast and queue its delivery to all active customers.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?int $adminId = null): GeneralNotification
    {
        $isOffer = $data['type'] === GeneralNotification::TYPE_OFFER;

        $generalNotification = $this->generalNotificationRepository->create([
            'admin_id' => $adminId,
            'type' => $data['type'],
            'offer_id' => $isOffer ? $data['offer_id'] : null,
            'title_en' => $data['title_en'],
            'title_ar' => $data['title_ar'],
            'message_en' => $data['message_en'],
            'message_ar' => $data['message_ar'],
            'status' => GeneralNotification::STATUS_PENDING,
        ]);

        DispatchGeneralNotificationJob::dispatch($generalNotification->id);

        return $generalNotification;
    }

    /**
     * Snapshot the active customers and queue one delivery job per chunk.
     */
    public function dispatchChunks(GeneralNotification $generalNotification): void
    {
        $generalNotification->update([
            'status' => GeneralNotification::STATUS_PROCESSING,
            'started_at' => now(),
        ]);

        $chunks = Customer::query()->active()->orderBy('id')->pluck('id')->chunk(self::CHUNK_SIZE);

        if ($chunks->isEmpty()) {
            $generalNotification->update([
                'status' => GeneralNotification::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            return;
        }

        $generalNotification->update(['total_chunks' => $chunks->count()]);

        foreach ($chunks as $customerIds) {
            SendGeneralNotificationChunkJob::dispatch($generalNotification->id, $customerIds->values()->all());
        }
    }

    /**
     * Create in-app notifications for a chunk of customers and push to their devices.
     *
     * @param  array<int, int>  $customerIds
     */
    public function deliverToCustomers(GeneralNotification $generalNotification, array $customerIds): void
    {
        $alreadyNotified = Notification::query()
            ->where('general_notification_id', $generalNotification->id)
            ->where('notifiable_type', Customer::class)
            ->whereIn('notifiable_id', $customerIds)
            ->pluck('notifiable_id')
            ->all();

        $customers = Customer::query()
            ->active()
            ->whereIn('id', $customerIds)
            ->whereNotIn('id', $alreadyNotified)
            ->get(['id', 'current_language']);

        if ($customers->isNotEmpty()) {
            $notificationIds = $this->storeNotifications($generalNotification, $customers);

            $generalNotification->increment('recipients_count', $customers->count());

            if (config('notifications.send_to_firebase', true)) {
                $this->pushToCustomers($generalNotification, $customers, $notificationIds);
            }
        }

        $this->markChunkProcessed($generalNotification->id);
    }

    public function markChunkProcessed(int $generalNotificationId): void
    {
        GeneralNotification::query()->whereKey($generalNotificationId)->increment('processed_chunks');

        GeneralNotification::query()
            ->whereKey($generalNotificationId)
            ->where('status', GeneralNotification::STATUS_PROCESSING)
            ->whereColumn('processed_chunks', '>=', 'total_chunks')
            ->update([
                'status' => GeneralNotification::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @return array<int, int> notification id keyed by customer id
     */
    protected function storeNotifications(GeneralNotification $generalNotification, Collection $customers): array
    {
        return DB::transaction(function () use ($generalNotification, $customers) {
            $now = now();
            $data = json_encode($generalNotification->payload());

            Notification::query()->insert($customers->map(fn (Customer $customer) => [
                'notifiable_type' => Customer::class,
                'notifiable_id' => $customer->id,
                'type' => $generalNotification->type,
                'is_read' => false,
                'data' => $data,
                'general_notification_id' => $generalNotification->id,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());

            $notificationIds = Notification::query()
                ->where('general_notification_id', $generalNotification->id)
                ->where('notifiable_type', Customer::class)
                ->whereIn('notifiable_id', $customers->pluck('id'))
                ->pluck('id', 'notifiable_id')
                ->all();

            $translations = [];
            foreach ($notificationIds as $notificationId) {
                foreach (['en', 'ar'] as $locale) {
                    $translations[] = [
                        'notification_id' => $notificationId,
                        'locale' => $locale,
                        'title' => $generalNotification->titleFor($locale),
                        'message' => $generalNotification->messageFor($locale),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            NotificationTranslation::query()->insert($translations);

            return $notificationIds;
        });
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @param  array<int, int>  $notificationIds
     */
    protected function pushToCustomers(GeneralNotification $generalNotification, Collection $customers, array $notificationIds): void
    {
        $tokensByCustomer = DeviceToken::query()
            ->whereIn('customer_id', $customers->pluck('id'))
            ->whereNotNull('token')
            ->where('token', '!=', '')
            ->get(['customer_id', 'token'])
            ->groupBy('customer_id');

        if ($tokensByCustomer->isEmpty()) {
            return;
        }

        try {
            $firebase = App::make(FirebaseService::class);
        } catch (\Throwable $e) {
            Log::warning('General notification push skipped: Firebase unavailable', [
                'general_notification_id' => $generalNotification->id,
                'error' => $e->getMessage(),
            ]);
            $generalNotification->increment('push_failed_count', $tokensByCustomer->flatten()->count());

            return;
        }

        $messages = [];
        foreach ($customers as $customer) {
            $tokens = $tokensByCustomer->get($customer->id)?->pluck('token')->unique() ?? collect();
            $locale = $customer->current_language === 'ar' ? 'ar' : 'en';
            $data = array_merge($generalNotification->payload(), [
                'type' => $generalNotification->type,
                'notification_id' => $notificationIds[$customer->id] ?? '',
            ]);

            foreach ($tokens as $token) {
                $messages[] = [
                    'token' => $token,
                    'title' => $generalNotification->titleFor($locale),
                    'body' => $generalNotification->messageFor($locale),
                    'data' => $data,
                ];
            }
        }

        try {
            $results = $firebase->sendMessagesConcurrently($messages, (int) config('notifications.push_concurrency', 50));
        } catch (\Throwable $e) {
            Log::warning('General notification push failed', [
                'general_notification_id' => $generalNotification->id,
                'error' => $e->getMessage(),
            ]);
            $generalNotification->increment('push_failed_count', count($messages));

            return;
        }

        $sent = 0;
        $failed = 0;
        $invalidTokens = [];

        foreach ($messages as $key => $message) {
            $result = $results[$key] ?? ['success' => false, 'invalid_token' => false];

            if ($result['success']) {
                $sent++;
                continue;
            }

            $failed++;
            if ($result['invalid_token']) {
                $invalidTokens[] = $message['token'];
            }
        }

        if (!empty($invalidTokens)) {
            DeviceToken::query()->whereIn('token', array_unique($invalidTokens))->delete();
        }

        if ($sent > 0) {
            $generalNotification->increment('push_sent_count', $sent);
        }
        if ($failed > 0) {
            $generalNotification->increment('push_failed_count', $failed);
        }
    }
}
