<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\WorkspaceNotification;
use App\Services\EmailBranding;
use App\Services\SystemMailSettings;
use App\Services\TicketEmailAvailability;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;
use Throwable;

class WorkspaceAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public WorkspaceNotification $workspaceNotification)
    {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function failed(Throwable $exception): void
    {
        app(TicketEmailAvailability::class)->disableTicketEmailFeatures($exception);
    }

    public function toMail(User $notifiable): MailMessage
    {
        app(SystemMailSettings::class)->apply();

        return (new MailMessage)
            ->subject($this->workspaceNotification->title)
            ->greeting('Hello '.$notifiable->displayName())
            ->line($this->workspaceNotification->message)
            ->action('View notifications', route('staff.notifications'))
            ->line('![Philippine Christian University logo](cid:pcu-logo@ticketech)')
            ->salutation('PCU IT Helpdesk')
            ->withSymfonyMessage(fn (Email $message) => app(EmailBranding::class)->embedLogos($message));
    }
}
