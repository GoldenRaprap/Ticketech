<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TicketSubmissionSettings
{
    public const DEFAULT_CLOSED_MESSAGE = 'Ticket submissions are temporarily unavailable during maintenance. Please try again later.';

    public function isOpen(): bool
    {
        return $this->setting('ticket.submission.enabled', '1') === '1';
    }

    public function closedMessage(): string
    {
        return $this->setting('ticket.submission.message', self::DEFAULT_CLOSED_MESSAGE);
    }

    private function setting(string $key, string $default): string
    {
        if (! Schema::hasTable('system_settings')) {
            return $default;
        }

        return DB::table('system_settings')->where('setting', $key)->value('value') ?? $default;
    }
}
