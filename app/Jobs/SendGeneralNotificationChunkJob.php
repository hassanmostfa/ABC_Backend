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

class SendGeneralNotificationChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * Must stay below the queue connection's retry_after (90s by default),
     * otherwise a slow chunk is handed to a second worker while still running.
     */
    public int $timeout = 80;

    /**
     * @param  array<int, int>  $customerIds
     */
    public function __construct(
        public int $generalNotificationId,
        public array $customerIds
    ) {
        $this->onQueue(config('notifications.queue'));
    }

    public function handle(GeneralNotificationService $service): void
    {
        $generalNotification = GeneralNotification::find($this->generalNotificationId);
        if (!$generalNotification) {
            return;
        }

        $service->deliverToCustomers($generalNotification, $this->customerIds);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendGeneralNotificationChunkJob failed', [
            'general_notification_id' => $this->generalNotificationId,
            'customers_in_chunk' => count($this->customerIds),
            'message' => $e->getMessage(),
        ]);

        app(GeneralNotificationService::class)->markChunkProcessed($this->generalNotificationId);
    }
}
