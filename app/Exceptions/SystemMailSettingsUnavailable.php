<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;
use Throwable;

final class SystemMailSettingsUnavailable extends RuntimeException implements ShouldntReport
{
    public const KEY_UNAVAILABLE_MESSAGE = 'The saved SMTP password cannot be decrypted. Restore the original APP_KEY that was used to save it in the project .env file or hosting secret, then restart the app. Do not generate a new key to decrypt the old password. If the original key is unavailable, configure a current APP_KEY first, then enter the SMTP app password again in Admin System Settings; saving a new password replaces the unreadable value.';

    public const EMAIL_NOT_CONFIGURED_MESSAGE = 'Automatic email delivery is not configured. Configure a working SMTP mailer and credentials in Admin System Settings. Ticket submissions and replies will remain paused until an administrator re-enables them.';

    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
