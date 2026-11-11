<?php

namespace App\Notifications;

use App\Services\EmailBranding;
use App\Services\SystemMailSettings;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\Mime\Email;

class StaffPasswordReset extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        app(SystemMailSettings::class)->apply();

        return parent::toMail($notifiable)
            ->line('![Philippine Christian University logo](cid:pcu-logo@ticketech)')
            ->salutation('PCU IT Helpdesk')
            ->withSymfonyMessage(fn (Email $message) => app(EmailBranding::class)->embedLogos($message));
    }
}
