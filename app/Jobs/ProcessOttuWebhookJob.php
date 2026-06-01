<?php

namespace App\Jobs;

use App\Models\PaymentGatewayEvent;
use App\Services\OttuPaymentProcessor;
use App\Services\OttuService;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessOttuWebhookJob
{
    use Dispatchable, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload)
    {
    }

    public function handle(OttuService $ottuService, OttuPaymentProcessor $ottuPaymentProcessor): void
    {
        $payload = $this->payload;
        $sessionId = $payload['session_id'] ?? null;
        $orderNo = $payload['order_no'] ?? null;

        if (!$sessionId || !$ottuService->verifySignature($payload)) {
            return;
        }

        $pgParams = is_array($payload['pg_params'] ?? null) ? $payload['pg_params'] : [];

        try {
            PaymentGatewayEvent::create([
                'provider' => 'ottu',
                'event_type' => 'webhook',
                'track_id' => $sessionId,
                'receipt_id' => $pgParams['receipt_no'] ?? null,
                'payload' => $payload,
                'received_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Ottu webhook: failed to persist gateway event', [
                'session_id' => $sessionId,
                'message' => $e->getMessage(),
            ]);
        }

        $statusResult = $ottuService->buildStatusResultFromWebhook($payload, $sessionId);
        if ($statusResult['requested_order_id'] === null && $orderNo !== null) {
            $statusResult['requested_order_id'] = $orderNo;
        }

        try {
            $processResult = $ottuPaymentProcessor->processVerifiedPayment(
                $sessionId,
                $statusResult,
                $pgParams['receipt_no'] ?? null,
                $orderNo
            );

            Log::info('Ottu webhook processed (async)', [
                'session_id' => $sessionId,
                'order_no' => $orderNo,
                'processed' => $processResult['processed'] ?? false,
                'outcome' => $processResult['payment_status'] ?? ($processResult['reason'] ?? null),
                'is_failed' => $statusResult['is_failed'] ?? false,
            ]);
        } catch (\Throwable $e) {
            Log::error('Ottu webhook async processing failed', [
                'session_id' => $sessionId,
                'order_no' => $orderNo,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
