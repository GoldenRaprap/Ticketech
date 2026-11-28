<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketMisuseReport;
use App\Models\User;
use App\Models\WorkspaceNotification;
use RuntimeException;

class ClientConductScreening
{
    /**
     * @var list<string>
     */
    private const THREAT_PHRASES = [
        'i will hurt you',
        'i will harm you',
        'i will kill you',
        'i ll hurt you',
        'i ll harm you',
        'i ll kill you',
        'i am going to hurt you',
        'i am going to kill you',
        'i m going to hurt you',
        'i m going to kill you',
        'you will regret this',
        'you ll regret this',
        'papatayin kita',
        'sasaktan kita',
    ];

    /**
     * @var list<string>
     */
    private const ABUSIVE_PHRASES = [
        'fuck you',
        'shut up',
        'you are an idiot',
        'you re an idiot',
        'you are stupid',
        'you re stupid',
        'you are useless',
        'you re useless',
        'you are an asshole',
        'you re an asshole',
        'bobo ka',
        'tanga ka',
        'gago ka',
        'putang ina mo',
        'walang kwenta ka',
    ];

    public function reportIfFlagged(Ticket $ticket, string $message, string $messageSource): ?TicketMisuseReport
    {
        $normalizedMessage = preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($message));

        if (! is_string($normalizedMessage)) {
            throw new RuntimeException('Unable to normalize client message for conduct screening.');
        }

        $threatSignals = $this->matchedPhrases($normalizedMessage, self::THREAT_PHRASES);
        $abusiveSignals = $this->matchedPhrases($normalizedMessage, self::ABUSIVE_PHRASES);
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
