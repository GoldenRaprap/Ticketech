<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class TicketNewCategoryRecommendations
{
    private const MINIMUM_UNMATCHED_TICKETS = 3;

    private const MAXIMUM_RECENT_UNMATCHED_TICKETS = 500;

    private const MAXIMUM_SUGGESTIONS = 5;

    public function __construct(private TicketClassificationPhraseExtractor $phraseExtractor) {}

    /**
     * @return list<array{
     *     keyword: string,
     *     category_name: string,
     *     ticket_count: int,
     *     ticket_ids: list<int>,
     *     examples: list<string>
     * }>
     */
    public function suggestions(): array
    {
        $tickets = Ticket::query()
            ->whereNull('department_id')
            ->whereNull('category_id')
            ->whereNotIn('status', ['Resolved', 'Closed'])
            ->whereHas('events', function (Builder $query): void {
                $query
                    ->where('event', 'automated_triage')
                    ->where('properties->routing_status', 'needs_department');
            })
            ->orderByDesc('id')
            ->limit(self::MAXIMUM_RECENT_UNMATCHED_TICKETS)
            ->get(['id', 'subject']);

        if ($tickets->isEmpty()) {
            return [];
        }

        $existingPhrases = $this->existingCategoryPhrases();
        $ticketsByPhrase = [];

        foreach ($tickets as $ticket) {
            foreach ($this->phraseExtractor->extract($ticket->subject) as $phrase) {
                if (! isset($existingPhrases[$phrase])) {
                    $ticketsByPhrase[$phrase][$ticket->id] = $ticket->subject;
                }
            }
        }

        $suggestions = [];

        foreach ($ticketsByPhrase as $phrase => $subjects) {
            $ticketCount = count($subjects);

            if ($ticketCount < self::MINIMUM_UNMATCHED_TICKETS) {
                continue;
            }

            $suggestions[] = [
                'keyword' => $phrase,
                'category_name' => Str::headline($phrase),
                'ticket_count' => $ticketCount,
                'ticket_ids' => array_map('intval', array_keys($subjects)),
                'examples' => array_slice(array_values($subjects), 0, 3),
            ];
        }

        usort($suggestions, function (array $first, array $second): int {
            return $second['ticket_count'] <=> $first['ticket_count']
                ?: count(explode(' ', $second['keyword'])) <=> count(explode(' ', $first['keyword']))
                ?: strcmp($first['keyword'], $second['keyword']);
        });

        return array_slice($suggestions, 0, self::MAXIMUM_SUGGESTIONS);
    }

    /**
     * @return array{
     *     keyword: string,
     *     category_name: string,
     *     ticket_count: int,
     *     ticket_ids: list<int>,
     *     examples: list<string>
     * }|null
     */
    public function findByKeyword(string $keyword): ?array
    {
        $keyword = mb_strtolower(trim($keyword));

        foreach ($this->suggestions() as $suggestion) {
            if ($suggestion['keyword'] === $keyword) {
                return $suggestion;
            }
        }

        return null;
    }

    /**
     * @return array<string, true>
     */
    private function existingCategoryPhrases(): array
    {
        $phrases = [];
        $categories = Category::query()->get(['name', 'description', 'classification_keywords']);

        foreach ($categories as $category) {
            foreach ($this->phraseExtractor->extract($category->name.' '.($category->description ?? '')) as $phrase) {
                $phrases[$phrase] = true;
            }

            foreach ($this->parseKeywords($category->classification_keywords ?? '') as $phrase) {
                $phrases[$phrase] = true;
            }
        }

        return $phrases;
    }

    /**
     * @return list<string>
     */
    private function parseKeywords(string $keywordList): array
    {
        $keywords = preg_split('/[\r\n,]+/', mb_strtolower($keywordList), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter(array_map('trim', $keywords ?: [])));
    }
}
