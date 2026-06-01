<?php

namespace App\Jobs;

use App\Services\OttuService;
use App\Services\WalletChargeService;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessOttuWalletChargeWebhookJob
{
    use Dispatchable, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload)
    {
    }

    public function handle(OttuService $ottuService, WalletChargeService $walletChargeService): void
    {
        $payload = $this->payload;

        if (!$ottuService->verifySignature($payload)) {
            return;
        }

        $reference = $payload['order_no']
            ?? $payload['requested_order_id']
            ?? null;

        if (is_string($reference)) {
            $reference = preg_split('/[?&]/', $reference)[0] ?? $reference;
            $reference = trim($reference) ?: null;
        }

        Log::info('Ottu wallet charge webhook processed (async)', ['reference' => $reference]);

        $payment = $walletChargeService->findByReference($reference ?? '');
        if (!$payment) {
            Log::warning('Ottu wallet charge webhook: wallet charge not found', ['reference' => $reference]);

            return;
        }

        if ($ottuService->isSuccessfulPayment($payload['result'] ?? null, $payload['state'] ?? null, $payload)) {
            $walletChargeService->processSuccess($payment);
        } elseif (!config('services.ottu.enable_pending_status', false)) {
            $walletChargeService->processCancel($payment);
        }
    }
}
