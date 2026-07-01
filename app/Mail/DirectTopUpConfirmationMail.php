<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DirectTopUpConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly array $data) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->data['subject'] ?? translate('direct_topup_order_completed'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email-templates.direct-topup-confirmation',
            with: ['data' => $this->data],
        );
    }
}
