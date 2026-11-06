<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TicketSubmissionSettings
{
    public const ENABLED_SETTING = 'ticket.submission.enabled';

    public const MESSAGE_SETTING = 'ticket.submission.message';

    public const DEFAULT_CLOSED_MESSAGE = 'Ticket submissions are temporarily unavailable during maintenance. Please try again later.';

    public function isOpen(): bool
    {
        return $this->setting(self::ENABLED_SETTING, '1') === '1';
    }

    public function closedMessage(): string
    {
        return $this->setting(self::MESSAGE_SETTING, self::DEFAULT_CLOSED_MESSAGE);
    }

    private function setting(string $key, string $default): string
    {
        if (! Schema::hasTable('system_settings')) {
            return $default;
        }

        return DB::table('system_settings')->where('setting', $key)->value('value') ?? $default;
    }
}
