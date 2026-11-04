<?php

namespace App\Notifications;

use App\Services\SystemMailSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketEmailVerification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $requesterName,
        public string $verificationUrl,
        public string $expiresAt,
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

        return (new MailMessage)
            ->subject('Verify your email to complete your PCU Helpdesk request')
            ->greeting('Hello '.$this->requesterName)
            ->line('Confirm that you can receive email at this address to complete your support request.')
            ->action('Confirm email and submit ticket', $this->verificationUrl)
            ->line('This secure link expires on '.$this->expiresAt.'.')
            ->line('We will create your ticket and send its ticket number and PDF confirmation only after you confirm your email.')
            ->line('If you did not submit this request, you can ignore this message.')
            ->salutation('PCU IT Helpdesk');
    }
}
