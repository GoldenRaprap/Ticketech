<?php

namespace App\Services;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

class ImmediateNotificationSender
{
    public function sendToEmail(string $email, Notification $notification): void
    {
        $this->send(NotificationFacade::route('mail', $email), $notification);
    }

    public function send(object $notifiable, Notification $notification): void
    {
        try {
            NotificationFacade::sendNow($notifiable, $notification);
        } catch (Throwable $exception) {
            if (method_exists($notification, 'failed')) {
                $notification->failed($exception);
            }

            throw $exception;
        }
    }
}
