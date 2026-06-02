<?php

namespace App\Jobs;

use App\Models\Order;
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
        public ?int $checkoutId = null,
        public ?int $orderId = null,
    ) {}

    public function handle(SmsBoxService $smsBoxService): void
    {
        if ($this->orderId !== null) {
            $this->sendForOrder($smsBoxService);

            return;
        }

        if ($this->checkoutId !== null) {
            $this->sendForCheckout($smsBoxService);
        }
    }

    private function sendForCheckout(SmsBoxService $smsBoxService): void
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

        $this->deliverSms(
            $smsBoxService,
            (string) $checkout->customer->phone,
            $checkout->customer->current_language ?? null,
            $checkout->order_number,
            (float) $checkout->amount_due,
            $checkout->payment_link,
            ['checkout_id' => $checkout->id, 'customer_id' => $checkout->customer_id]
        );
    }

    private function sendForOrder(SmsBoxService $smsBoxService): void
    {
        $order = Order::query()
            ->with(['customer', 'invoice.payments'])
            ->find($this->orderId);

        if (!$order || $order->payment_method !== 'online_link') {
            return;
        }

        $invoice = $order->invoice;
        $paymentLink = $invoice?->payment_link;
        $customer = $order->customer;

        if (!$paymentLink || !$customer) {
            return;
        }

        $totalPaid = $invoice
            ? (float) $invoice->payments->where('status', 'completed')->sum('amount')
            : 0.0;
        $amountDue = max(0, (float) ($invoice->amount_due ?? 0) - $totalPaid);

        $this->deliverSms(
            $smsBoxService,
            (string) $customer->phone,
            $customer->current_language ?? null,
            (string) $order->order_number,
            $amountDue,
            $paymentLink,
            ['order_id' => $order->id, 'customer_id' => $order->customer_id]
        );
    }

    /**
     * @param  array<string, int|null>  $logContext
     */
    private function deliverSms(
        SmsBoxService $smsBoxService,
        string $phoneRaw,
        ?string $currentLanguage,
        string $orderNumber,
        float $amountDue,
        string $paymentLink,
        array $logContext,
    ): void {
        $phone = preg_replace('/\D+/', '', $phoneRaw);
        if ($phone === '') {
            Log::warning('SendPaymentLinkSmsJob: customer has no phone', $logContext);

            return;
        }

        $locale = in_array($currentLanguage, ['ar', 'en'], true) ? $currentLanguage : 'ar';
        $amount = number_format($amountDue, 3);
        $message = $this->buildMessage($locale, $orderNumber, $amount, $paymentLink);

        $isProduction = getSetting('is_production', '0') === '1';
        if (!$isProduction) {
            Log::info('SendPaymentLinkSmsJob: skipped SMS in test mode', array_merge($logContext, [
                'phone' => $phone,
                'message' => $message,
            ]));

            return;
        }

        $result = $smsBoxService->send($phone, $message);

        if (!$result['success']) {
            Log::warning('SendPaymentLinkSmsJob: SMS delivery failed', array_merge($logContext, [
                'phone' => $phone,
            ]));
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
