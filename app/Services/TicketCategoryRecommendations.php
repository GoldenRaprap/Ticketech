<?php

namespace App\Services;

use App\Models\Category;
use App\Models\TicketEvent;

class TicketCategoryRecommendations
{
    private const MINIMUM_CORRECTIONS = 3;

    private const MAXIMUM_RECENT_CORRECTIONS = 500;

    public function __construct(private TicketClassificationPhraseExtractor $phraseExtractor) {}

    /**
     * @return array<int, list<array{keyword: string, corrections: int}>>
     */
    public function recommendations(): array
    {
        $correctionEvents = TicketEvent::query()
            ->with('ticket:id,subject')
            ->where('event', 'category_changed')
            ->orderByDesc('id')
            ->limit(self::MAXIMUM_RECENT_CORRECTIONS)
            ->get()
            ->unique('ticket_id')
            ->filter(fn (TicketEvent $event): bool => ($event->properties['category_feedback'] ?? false) === true)
            ->values();

        $categoryIds = $correctionEvents
            ->map(fn (TicketEvent $event): mixed => $event->properties['new_category_id'] ?? null)
            ->filter(fn (mixed $categoryId): bool => is_numeric($categoryId))
            ->map(fn (mixed $categoryId): int => (int) $categoryId)
            ->unique()
            ->values();

        if ($categoryIds->isEmpty()) {
            return [];
        }

        $categories = Category::query()
            ->whereIn('id', $categoryIds)
            ->where('is_active', true)
            ->whereNotNull('department_id')
            ->whereHas('department', fn ($query) => $query->where('is_active', true))
            ->get(['id', 'name', 'description', 'classification_keywords'])
            ->keyBy('id');
        $existingPhrases = [];

        foreach ($categories as $category) {
            $existingPhrases[$category->id] = array_fill_keys([
                ...$this->parseKeywords($category->classification_keywords ?? ''),
                ...$this->phraseExtractor->extract(
                    $category->name.' '.($category->description ?? ''),
                ),
            ], true);
        }

        $counts = [];

        foreach ($correctionEvents as $event) {
            $ticket = $event->ticket;
            $categoryId = (int) ($event->properties['new_category_id'] ?? 0);

            if ($ticket === null || ! $categories->has($categoryId)) {
                continue;
            }

            foreach ($this->phraseExtractor->extract($ticket->subject) as $phrase) {
                if (! isset($existingPhrases[$categoryId][$phrase])) {
                    $counts[$categoryId][$phrase][$ticket->id] = true;
                }
            }
        }

        $recommendations = [];

        foreach ($counts as $categoryId => $phrases) {
            $recommendations[$categoryId] = [];

            foreach ($phrases as $phrase => $ticketIds) {
                $correctionCount = count($ticketIds);

                if ($correctionCount >= self::MINIMUM_CORRECTIONS) {
                    $recommendations[$categoryId][] = [
                        'keyword' => $phrase,
                        'corrections' => $correctionCount,
                    ];
                }
            }

            usort($recommendations[$categoryId], function (array $first, array $second): int {
                return $second['corrections'] <=> $first['corrections']
                    ?: count(explode(' ', $second['keyword'])) <=> count(explode(' ', $first['keyword']))
                    ?: mb_strlen($second['keyword']) <=> mb_strlen($first['keyword'])
                    ?: strcmp($first['keyword'], $second['keyword']);
            });

            $recommendations[$categoryId] = array_slice($recommendations[$categoryId], 0, 3);
        }

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
}
