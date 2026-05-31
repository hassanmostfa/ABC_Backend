<?php

namespace App\Jobs;

use App\Models\OrderCheckout;
use App\Services\SmsBoxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPaymentLinkSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $checkoutId
    ) {}

    public function handle(SmsBoxService $smsBoxService): void
    {
        $checkout = OrderCheckout::query()
            ->with('customer')
            ->find($this->checkoutId);

        if (!$checkout || $checkout->source !== 'call_center') {
            return;
        }

        if (!$checkout->payment_link || !$checkout->customer) {
            return;
        }

        $phone = preg_replace('/\D+/', '', (string) $checkout->customer->phone);
        if ($phone === '') {
            Log::warning('SendPaymentLinkSmsJob: customer has no phone', [
                'checkout_id' => $checkout->id,
                'customer_id' => $checkout->customer_id,
            ]);

            return;
        }

        $locale = in_array($checkout->customer->current_language, ['ar', 'en'], true)
            ? $checkout->customer->current_language
            : 'ar';

        $amount = number_format((float) $checkout->amount_due, 2);
        $message = $this->buildMessage(
            $locale,
            $checkout->order_number,
            $amount,
            $checkout->payment_link
        );

        $isProduction = getSetting('is_production', '0') === '1';
        if (!$isProduction) {
            Log::info('SendPaymentLinkSmsJob: skipped SMS in test mode', [
                'checkout_id' => $checkout->id,
                'phone' => $phone,
                'message' => $message,
            ]);

            return;
        }

        $result = $smsBoxService->send($phone, $message);

        if (!$result['success']) {
            Log::warning('SendPaymentLinkSmsJob: SMS delivery failed', [
                'checkout_id' => $checkout->id,
                'customer_id' => $checkout->customer_id,
                'phone' => $phone,
            ]);
        }
    }

    protected function buildMessage(string $locale, string $orderNumber, string $amount, string $paymentLink): string
    {
        // LRM forces left-to-right rendering so iOS/Android detect the URL as tappable in Arabic SMS.
        $link = "\u{200E}" . trim($paymentLink);

        if ($locale === 'en') {
            return "Your order {$orderNumber} is ready for payment ({$amount} KWD).\n\nPay here:\n{$link}";
        }

        return "طلبك رقم {$orderNumber} جاهز للدفع ({$amount} د.ك).\n\n{$link}";
    }
}
