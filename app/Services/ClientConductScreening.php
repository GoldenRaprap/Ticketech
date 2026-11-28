<?php

namespace App\Services;

use App\Models\ClientConductPhrase;
use App\Models\Ticket;
use App\Models\TicketMisuseReport;
use App\Models\User;
use App\Models\WorkspaceNotification;
use RuntimeException;

class ClientConductScreening
{
    public function reportIfFlagged(Ticket $ticket, string $message, string $messageSource): ?TicketMisuseReport
    {
        $normalizedMessage = $this->normalizeText($message);
        $phrases = ClientConductPhrase::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['phrase', 'reason']);
        $threatSignals = $this->matchedPhrases(
            $normalizedMessage,
            $phrases->where('reason', 'threatening')->pluck('phrase')->all(),
        );
        $abusiveSignals = $this->matchedPhrases(
            $normalizedMessage,
            $phrases->where('reason', 'abusive')->pluck('phrase')->all(),
        );
        $reason = $threatSignals !== [] ? 'threatening' : 'abusive';
        $matchedSignals = array_values(array_unique([...$threatSignals, ...$abusiveSignals]));

        if ($matchedSignals === []) {
            return null;
        }

        $report = $ticket->misuseReports()->create([
            'reporter_id' => null,
            'reason' => $reason,
            'details' => sprintf(
                'Automated screening flagged possible %s in the %s. Matched indicators: %s. This is a review flag only; no warning or block was applied.',
                $reason,
                $messageSource,
                implode(', ', $matchedSignals),
            ),
            'status' => 'pending',
        ]);

        User::query()
            ->where('role', 'admin')
            ->where('employment_status', 'active')
            ->where('staff_approval_status', 'approved')
            ->get(['id'])
            ->each(fn (User $admin) => WorkspaceNotification::create([
                'user_id' => $admin->id,
                'ticket_id' => $ticket->id,
                'type' => 'client_report',
                'title' => 'Automated client conduct report',
                'message' => "{$ticket->ticket_number} · {$reason} flagged for review",
            ]));

        return $report;
    }

    public function normalizeText(string $text): string
    {
        $normalizedText = preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($text));

        if (! is_string($normalizedText)) {
            throw new RuntimeException('Unable to normalize client conduct text.');
        }

        return trim($normalizedText);
    }

    /**
     * @param  list<string>  $phrases
     * @return list<string>
     */
    private function matchedPhrases(string $message, array $phrases): array
    {
        $matches = [];

        foreach ($phrases as $phrase) {
            $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($phrase, '/').'(?![\p{L}\p{N}])/u';

            if (preg_match($pattern, $message) === 1) {
                $matches[] = $phrase;
            }
        }

        return $matches;
    }
}
