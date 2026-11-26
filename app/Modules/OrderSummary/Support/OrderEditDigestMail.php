<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Support;

use App\Modules\Identity\Models\MailService;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Order Summary's edit mail (MailDigest): its sender is the mail service it goes through. */
final class OrderEditDigestMail extends Mailable
{
    public function __construct(public string $body, public MailService $service, public int $count, public ?string $subjectLine = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->service->from_address ?: $this->service->smtp_username, $this->service->from_name ?: null),
            subject: $this->subjectLine ?? __('ordersummary.mail.subject', ['count' => $this->count]),
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->body);
    }
}
