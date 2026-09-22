<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\ServiceVoucher;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ServiceVoucherProviderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ServiceVoucher $voucher,
        public Customer $customer
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New service voucher - {$this->voucher->code}",
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtml(),
        );
    }

    protected function buildHtml(): string
    {
        $serviceName = e($this->voucher->service_name);
        $code = e($this->voucher->code);
        $amount = e(number_format((float) $this->voucher->amount, 3, '.', ''));
        $currency = e((string) config('services.ottu.currency', 'KWD'));
        $customerName = e($this->customer->name ?: 'Customer');
        $customerPhone = e($this->customer->phone ?: 'Not provided');
        $customerEmail = e($this->customer->email ?: 'Not provided');
        $paidAt = e(optional($this->voucher->created_at)->timezone('Asia/Kuwait')->format('Y-m-d H:i'));

        return <<<HTML
<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; line-height: 1.5; color: #222;">
  <p>A customer has purchased <strong>{$serviceName}</strong>.</p>
  <p><strong>Customer</strong><br>
  Name: {$customerName}<br>
  Phone: {$customerPhone}<br>
  Email: {$customerEmail}</p>
  <p><strong>Voucher</strong><br>
  Code: {$code}<br>
  Amount: {$amount} {$currency}<br>
  Paid at: {$paidAt}</p>
  <p>Regards,<br>ABC</p>
</body>
</html>
HTML;
    }
}
