<?php

namespace App\Notifications;

use App\Services\SystemMailSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketRequesterUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $requesterName,
        public string $ticketNumber,
        public string $ticketSubject,
        public string $updateMessage,
        public string $emailSubject,
        public ?string $ticketPdf = null,
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
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
            ->salutation('PCU IT Helpdesk');

        if ($this->ticketPdf !== null) {
            $mail->attachData($this->ticketPdf, 'ticket-'.$this->ticketNumber.'.pdf', [
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }
}
