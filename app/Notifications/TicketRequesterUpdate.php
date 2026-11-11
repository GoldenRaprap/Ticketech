<?php

namespace App\Notifications;

use App\Services\EmailBranding;
use App\Services\SystemMailSettings;
use App\Services\TicketEmailAvailability;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;
use Throwable;

class TicketRequesterUpdate extends Notification
{
    public function __construct(
        public string $requesterName,
        public string $ticketNumber,
        public string $ticketSubject,
        public string $updateMessage,
        public string $emailSubject,
        public ?string $ticketPdf = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function failed(Throwable $exception): void
    {
        app(TicketEmailAvailability::class)->disableTicketEmailFeatures($exception);
    }

    public function toMail(object $notifiable): MailMessage
    {
        app(SystemMailSettings::class)->apply();

        $mail = (new MailMessage)
            ->subject($this->emailSubject)
            ->greeting('Hello '.$this->requesterName)
            ->line($this->updateMessage)
            ->line("Ticket {$this->ticketNumber}: {$this->ticketSubject}")
            ->action('Check your ticket', route('ticket.check'))
            ->line('Please do not reply to this automated email. Use the ticket portal to add a response.')
            ->line('![Philippine Christian University logo](cid:pcu-logo@ticketech)')
            ->salutation('PCU IT Helpdesk')
            ->withSymfonyMessage(fn (Email $message) => app(EmailBranding::class)->embedLogos($message));

        if ($this->ticketPdf !== null) {
            $mail->attachData($this->ticketPdf, 'ticket-'.$this->ticketNumber.'.pdf', [
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }
}
