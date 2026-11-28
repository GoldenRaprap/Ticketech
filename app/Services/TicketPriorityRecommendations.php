<?php

namespace App\Services;

use App\Models\TicketEvent;

class TicketPriorityRecommendations
{
    private const MINIMUM_CORRECTIONS = 3;

    private const MAXIMUM_RECENT_CORRECTIONS = 500;

    public function __construct(private TicketClassificationPhraseExtractor $phraseExtractor) {}

    /**
     * @param  array<string, string>  $keywords
     * @return array<string, list<array{action: string, keyword: string, corrections: int}>>
     */
    public function recommendations(array $keywords): array
    {
        $recommendations = [
            'urgent' => [],
            'high' => [],
            'medium' => [],
            'low' => [],
        ];
        $existingKeywords = [];

        foreach ($keywords as $keywordList) {
            foreach ($this->parseKeywords($keywordList) as $keyword) {
                $existingKeywords[$keyword] = true;
            }
        }

        $correctionEvents = TicketEvent::query()
            ->with('ticket:id,subject')
            ->where('event', 'priority_changed')
            ->orderByDesc('id')
            ->limit(self::MAXIMUM_RECENT_CORRECTIONS)
            ->get();

        $counts = [];

        foreach ($correctionEvents->unique('ticket_id') as $event) {
            $ticket = $event->ticket;

            if ($ticket === null || ($event->properties['priority_feedback'] ?? false) !== true) {
                continue;
            }

            $properties = $event->properties ?? [];
            $previousPriority = $this->priorityKey($properties['previous_priority'] ?? null);
            $newPriority = $this->priorityKey($properties['new_priority'] ?? null);

            if ($previousPriority === null || $newPriority === null || $previousPriority === $newPriority) {
                continue;
            }

            foreach ($this->phraseExtractor->extract($ticket->subject) as $phrase) {
                if (! isset($existingKeywords[$phrase])) {
                    $counts['add'][$newPriority][$phrase][$ticket->id] = true;
                }
            }

            $matchedKeyword = mb_strtolower(trim((string) ($properties['matched_keyword'] ?? '')));

            if (
                $matchedKeyword !== ''
                && in_array($matchedKeyword, $this->parseKeywords($keywords[$previousPriority] ?? ''), true)
            ) {
                $counts['remove'][$previousPriority][$matchedKeyword][$ticket->id] = true;
            }
        }

        foreach ($recommendations as $priority => &$priorityRecommendations) {
            foreach (['add', 'remove'] as $action) {
                foreach ($counts[$action][$priority] ?? [] as $keyword => $ticketIds) {
                    $correctionCount = count($ticketIds);

                    if ($correctionCount >= self::MINIMUM_CORRECTIONS) {
                        $priorityRecommendations[] = [
                            'action' => $action,
                            'keyword' => $keyword,
                            'corrections' => $correctionCount,
                        ];
                    }
                }
            }

            usort($priorityRecommendations, function (array $first, array $second): int {
                return $second['corrections'] <=> $first['corrections']
                    ?: count(explode(' ', $second['keyword'])) <=> count(explode(' ', $first['keyword']))
                    ?: mb_strlen($second['keyword']) <=> mb_strlen($first['keyword'])
                    ?: strcmp($first['keyword'], $second['keyword'])
                    ?: strcmp($first['action'], $second['action']);
            });

            $priorityRecommendations = array_slice($priorityRecommendations, 0, 3);
        }

        unset($priorityRecommendations);

        return $recommendations;
    }

    /**
     * @return list<string>
     */
    private function parseKeywords(string $keywordList): array
    {
        $keywords = preg_split('/[\r\n,]+/', mb_strtolower($keywordList), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter(array_map('trim', $keywords ?: [])));
    }

    private function priorityKey(mixed $priority): ?string
    {
        if (! is_string($priority)) {
            return null;
        }

        $priority = mb_strtolower($priority);

        return in_array($priority, ['urgent', 'high', 'medium', 'low'], true)
            ? $priority
            : null;
    }
}
