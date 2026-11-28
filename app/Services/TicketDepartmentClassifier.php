<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;

class TicketDepartmentClassifier
{
    private const MINIMUM_MATCH_SCORE = 2;

    /**
     * @var list<string>
     */
    private const STOP_WORDS = [
        'a',
        'an',
        'and',
        'are',
        'at',
        'by',
        'error',
        'errors',
        'for',
        'from',
        'help',
        'i',
        'in',
        'is',
        'issue',
        'issues',
        'it',
        'me',
        'my',
        'of',
        'on',
        'problem',
        'problems',
        'request',
        'requests',
        'service',
        'support',
        'system',
        'that',
        'the',
        'this',
        'to',
        'with',
    ];

    public function resolve(
        string $subject,
        string $description,
    ): ?Category {
        $ticketTerms = $this->terms($subject.' '.$description);

        if ($ticketTerms === []) {
            return null;
        }

        $ticketText = mb_strtolower($subject.' '.$description);
        $bestScore = 0;
        $bestCategory = null;
        $hasTie = false;

        $categories = Category::query()
            ->where('is_active', true)
            ->whereNotNull('department_id')
            ->whereHas('department', fn (Builder $query) => $query->where('is_active', true))
            ->with('department:id,name,is_active')
            ->orderBy('id')
            ->get(['id', 'name', 'description', 'classification_keywords', 'department_id']);

        foreach ($categories as $category) {
            $score = count(array_intersect(
                $this->terms(implode(' ', [
                    $category->name,
                    $category->description,
                    $category->classification_keywords,
                ])),
                $ticketTerms,
            ));
            $categoryNamePattern = '/(?<![\pL\pN])'
                .str_replace(' ', '\s+', preg_quote(mb_strtolower($category->name), '/'))
                .'(?![\pL\pN])/iu';

            if (preg_match($categoryNamePattern, $ticketText) === 1) {
                $score += 2;
            }

            foreach ($this->parseKeywords($category->classification_keywords ?? '') as $keyword) {
                $keywordPattern = '/(?<![\pL\pN])'
                    .str_replace(' ', '\s+', preg_quote($keyword, '/'))
                    .'(?![\pL\pN])/iu';

                if (preg_match($keywordPattern, $ticketText) === 1) {
                    $score += 2;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestCategory = $category;
                $hasTie = false;
            } elseif ($score === $bestScore) {
                $hasTie = true;
            }
        }

        return $bestScore < self::MINIMUM_MATCH_SCORE || $hasTie
            ? null
            : $bestCategory;
    }

    /**
     * @return list<string>
     */
    private function terms(string $text): array
    {
        $terms = preg_split('/[^\pL\pN]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_filter(
            $terms ?: [],
            fn (string $term): bool => mb_strlen($term) > 2 && ! in_array($term, self::STOP_WORDS, true),
        )));
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
