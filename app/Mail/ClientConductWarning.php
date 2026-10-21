<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientConductWarning extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct() {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'PCU Helpdesk Conduct Warning',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.client-conduct-warning',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
