<?php

namespace App\Mail;

use App\Models\Complaint;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ComplaintAcknowledgementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Complaint $complaint)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Complaint Acknowledgement - {$this->complaint->reference_number}",
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
        $name = e($this->complaint->customer_name ?: 'Customer');
        $date = e(optional($this->complaint->complaint_date)->format('Y-m-d'));

        return <<<HTML
<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; line-height: 1.5; color: #222;">
  <p>Dear {$name},</p>
  <p>We have received your complaint and registered it under reference <strong>{$ref}</strong> on {$date}.</p>
  <p>Our team is reviewing the details and will follow up with you through an authorized channel.</p>
  <p>Please keep this reference number for future correspondence.</p>
  <p>Regards,<br>ABC Customer Care</p>
</body>
</html>
HTML;
    }
}
