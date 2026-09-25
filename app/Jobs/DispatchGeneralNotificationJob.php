<?php

namespace App\Jobs;

use App\Models\GeneralNotification;
use App\Services\Notification\GeneralNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchGeneralNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Single attempt: a retry would queue every chunk a second time.
     */
    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public int $generalNotificationId
    ) {
        $this->onQueue(config('notifications.queue'));
    }

    public function handle(GeneralNotificationService $service): void
    {
        $generalNotification = GeneralNotification::find($this->generalNotificationId);
        if (!$generalNotification || $generalNotification->status !== GeneralNotification::STATUS_PENDING) {
            return;
        }

        $service->dispatchChunks($generalNotification);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('DispatchGeneralNotificationJob failed', [
            'general_notification_id' => $this->generalNotificationId,
            'message' => $e->getMessage(),
        ]);

        GeneralNotification::query()
            ->whereKey($this->generalNotificationId)
            ->update(['status' => GeneralNotification::STATUS_FAILED]);
    }
}
