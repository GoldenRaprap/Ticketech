<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PublicFeatureAvailability
{
    public const KNOWLEDGE_BASE_ENABLED = 'knowledge_base.enabled';

    public const KNOWLEDGE_BASE_MESSAGE = 'knowledge_base.message';

    public const TICKET_REPLIES_ENABLED = 'ticket.replies.enabled';

    public const TICKET_REPLIES_MESSAGE = 'ticket.replies.message';

    public const DEFAULT_KNOWLEDGE_BASE_MESSAGE = 'The knowledge base is temporarily unavailable. Please try again later.';

    public const DEFAULT_TICKET_REPLIES_MESSAGE = 'Ticket replies are temporarily unavailable. Please contact the helpdesk if you need assistance.';

    public function knowledgeBaseIsEnabled(): bool
    {
        return $this->setting(self::KNOWLEDGE_BASE_ENABLED, '1') === '1';
    }

    public function knowledgeBaseMessage(): string
    {
        return $this->setting(self::KNOWLEDGE_BASE_MESSAGE, self::DEFAULT_KNOWLEDGE_BASE_MESSAGE);
    }

    public function ticketRepliesAreEnabled(): bool
    {
        return $this->setting(self::TICKET_REPLIES_ENABLED, '1') === '1';
    }

    public function ticketRepliesMessage(): string
    {
        return $this->setting(self::TICKET_REPLIES_MESSAGE, self::DEFAULT_TICKET_REPLIES_MESSAGE);
    }

    private function setting(string $key, string $default): string
    {
        if (! Schema::hasTable('system_settings')) {
            return $default;
        }

        return DB::table('system_settings')->where('setting', $key)->value('value') ?? $default;
    }
}
