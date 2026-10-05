<?php

namespace App\Mail;

use App\Models\Complaint;
use BackedEnum;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ComplaintActionRequiredMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Complaint $complaint)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Action required - new complaint {$this->complaint->reference_number}",
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
        $ref = e($this->complaint->reference_number);
        $date = e(optional($this->complaint->complaint_date)->format('Y-m-d'));
        $time = e($this->complaint->complaint_time ?: '—');
        $type = e($this->label($this->complaint->complaint_type));
        $severity = e($this->label($this->complaint->severity));
        $channel = e($this->label($this->complaint->receiving_channel));
        $status = e($this->label($this->complaint->status));
        $customer = e($this->complaint->customer_name ?: '—');
        $phone = e($this->complaint->customer_phone ?: '—');
        $email = e($this->complaint->customer_email ?: '—');
        $product = e($this->complaint->product_name ?: '—');
        $batch = e($this->complaint->batch_number ?: '—');
        $order = e($this->complaint->order_id ?: '—');
        $department = e($this->complaint->department ?: '—');
        $against = e($this->complaint->against ?: '—');
        $description = nl2br(e($this->complaint->description ?: '—'));
        $healthRisk = $this->complaint->consumer_health_risk ? 'Yes' : 'No';

        return <<<HTML
<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; line-height: 1.5; color: #222;">
  <p>A new complaint has been registered and needs action.</p>
  <p><strong>Reference:</strong> {$ref}<br>
  <strong>Date:</strong> {$date} {$time}<br>
  <strong>Status:</strong> {$status}<br>
  <strong>Type:</strong> {$type}<br>
  <strong>Severity:</strong> {$severity}<br>
  <strong>Channel:</strong> {$channel}<br>
  <strong>Consumer health risk:</strong> {$healthRisk}</p>
  <p><strong>Customer</strong><br>
  Name: {$customer}<br>
  Phone: {$phone}<br>
  Email: {$email}</p>
  <p><strong>Details</strong><br>
  Product: {$product}<br>
  Batch: {$batch}<br>
  Order: {$order}<br>
  Department: {$department}<br>
  Against: {$against}</p>
  <p><strong>Description</strong><br>{$description}</p>
  <p>Please review this complaint and take the required action.</p>
  <p>Regards,<br>ABC Customer Care</p>
</body>
</html>
HTML;
    }

    protected function label(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return str_replace('_', ' ', (string) $value->value);
        }

        if ($value === null || $value === '') {
            return '—';
        }

        return str_replace('_', ' ', (string) $value);
    }
}
