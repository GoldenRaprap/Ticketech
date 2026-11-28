<?php

namespace App\Services;

use App\Models\KnowledgeArticle;
use App\Models\KnowledgeArticleSuggestion;
use App\Models\Ticket;
use Illuminate\Support\Collection;

class KnowledgeArticleSuggestionGenerator
{
    private const STOP_WORDS = [
        'and', 'are', 'but', 'for', 'from', 'have', 'help', 'issue', 'issues',
        'please', 'problem', 'request', 'service', 'staff', 'student', 'system',
        'that', 'the', 'this', 'with', 'your', 'cannot', 'missing', 'need',
    ];

    public function generateForTicket(Ticket $ticket): ?KnowledgeArticleSuggestion
    {
        if (! in_array($ticket->status, ['Resolved', 'Closed'], true)) {
            return null;
        }

        $ticket->loadMissing(['category:id,name', 'comments']);
        $terms = $this->terms($ticket->subject.' '.$ticket->description);

        if ($terms === []) {
            return null;
        }

        $publishedArticles = KnowledgeArticle::query()
            ->where('is_published', true)
            ->get([
                'id',
                'title',
                'draft_title',
                'category',
                'draft_category',
                'summary',
                'draft_summary',
                'body',
                'draft_body',
            ]);

        $articleMatch = $publishedArticles
            ->map(function (KnowledgeArticle $article) use ($terms): array {
                $articleTerms = $this->terms(implode(' ', array_filter([
                    $article->title,
                    $article->draft_title,
                    $article->summary,
                    $article->draft_summary,
                    $article->body,
                    $article->draft_body,
                ])));
                $sharedTerms = array_values(array_intersect($terms, $articleTerms));
                $union = array_unique([...$terms, ...$articleTerms]);
                $score = $union === [] ? 0 : (int) round(count($sharedTerms) / count($union) * 100);

                return [
                    'article' => $article,
                    'shared_terms' => $sharedTerms,
                    'score' => $score,
                ];
            })
            ->filter(fn (array $match): bool => count($match['shared_terms']) >= 2
                && $match['score'] >= 15)
            ->sortByDesc('score')
            ->first();

        if ($articleMatch !== null) {
            return $this->createUpdateSuggestion(
                $ticket,
                $articleMatch['article'],
                $articleMatch['shared_terms'],
                $articleMatch['score'],
            );
        }

        $relatedTickets = Ticket::query()
            ->whereKeyNot($ticket->id)
            ->whereIn('status', ['Resolved', 'Closed'])
            ->when($ticket->department_id !== null, fn ($query) => $query->where('department_id', $ticket->department_id))
            ->when($ticket->category_id !== null, fn ($query) => $query->where('category_id', $ticket->category_id))
            ->latest('id')
            ->limit(50)
            ->get(['id', 'ticket_number', 'department_id', 'category_id', 'subject', 'description']);

        $matches = $relatedTickets
            ->filter(function (Ticket $related) use ($terms): bool {
                $shared = array_intersect($terms, $this->terms($related->subject.' '.$related->description));

                return count($shared) >= 2;
            })
            ->take(4)
            ->prepend($ticket);

        if ($matches->count() < 2) {
            return null;
        }

        $sourceTickets = $matches->values();
        $resolutionSteps = $this->resolutionSteps($sourceTickets);

        if ($resolutionSteps === []) {
            return null;
        }

        $category = $ticket->category?->name ?? 'General support';
        $title = $this->articleTitle($ticket->subject);
        $summary = $this->summary($ticket, $resolutionSteps);
        $body = $this->newArticleBody($ticket, $sourceTickets, $resolutionSteps);
        $fingerprint = hash('sha256', 'new|'.mb_strtolower($category).'|'.mb_strtolower($title));

        return KnowledgeArticleSuggestion::query()->firstOrCreate(
            ['fingerprint' => $fingerprint],
            [
                'target_article_id' => null,
                'title' => $title,
                'category' => $category,
                'summary' => $summary,
                'proposed_body' => $body,
                'source_ticket_ids' => $sourceTickets->pluck('id')->all(),
                'shared_terms' => array_values(array_intersect(
                    $terms,
                    ...$sourceTickets->skip(1)->map(fn (Ticket $source): array => $this->terms($source->subject.' '.$source->description))->all(),
                )),
                'similarity_score' => 100,
                'status' => 'pending',
            ],
        );
    }

    private function createUpdateSuggestion(
        Ticket $ticket,
        KnowledgeArticle $article,
        array $sharedTerms,
        int $score,
    ): ?KnowledgeArticleSuggestion {
        $previousUpdateBodies = KnowledgeArticleSuggestion::query()
            ->where('target_article_id', $article->id)
            ->pluck('proposed_body');
        $existingArticleContent = implode(' ', array_filter([
            $article->body,
            $article->draft_body,
            ...$previousUpdateBodies->all(),
        ]));
        $existingArticleText = $this->normalizedText($existingArticleContent);
        $allResolutionSteps = $this->resolutionSteps(collect([$ticket]));

        if ($allResolutionSteps === []) {
            return null;
        }

        $resolutionSteps = array_values(array_filter(
            $allResolutionSteps,
            fn (string $step): bool => ! str_contains($existingArticleText, $this->normalizedText($step)),
        ));
        $subject = $this->plainText($ticket->subject);
        $description = mb_substr($this->plainText($ticket->description), 0, 1000);
        $newSubject = $subject !== ''
            && ! str_contains($existingArticleText, $this->normalizedText($subject));
        $newDescription = $description !== ''
            && ! str_contains($existingArticleText, $this->normalizedText($description));

        if ($resolutionSteps === [] && ! $newSubject && ! $newDescription) {
            return null;
        }

        $section = '<h2>New information from resolved ticket: '.e($subject).'</h2>';

        if ($newDescription) {
            $section .= '<p><strong>Reported concern:</strong> '.e($description).'</p>';
        }

        if ($resolutionSteps !== []) {
            $section .= '<p><strong>Newly confirmed resolution steps</strong></p>'
                .'<ol>'.implode('', array_map(
                    fn (string $step): string => '<li>'.e($step).'</li>',
                    $resolutionSteps,
                )).'</ol>';
        }

        $summary = $resolutionSteps !== []
            ? 'New resolution for '.$subject.': '.$resolutionSteps[0]
            : 'New reported concern for '.$subject.': '.$description;
        $summary = mb_strlen($summary) > 500 ? mb_substr($summary, 0, 497).'...' : $summary;
        $fingerprint = hash('sha256', 'update|'.$article->id.'|'.$ticket->id.'|'.hash('sha256', $section));

        return KnowledgeArticleSuggestion::query()->firstOrCreate(
            ['fingerprint' => $fingerprint],
            [
                'target_article_id' => $article->id,
                'title' => $article->draft_title ?? $article->title,
                'category' => $article->draft_category ?? $article->category,
                'summary' => $summary,
                'proposed_body' => $section,
                'source_ticket_ids' => [$ticket->id],
                'shared_terms' => $sharedTerms,
                'similarity_score' => $score,
                'status' => 'pending',
            ],
        );
    }

    private function normalizedText(string $text): string
    {
        return mb_strtolower($this->plainText($text));
    }

    private function plainText(string $text): string
    {
        $plainText = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $plainText) ?? '');
    }

    private function resolutionSteps(Collection $tickets): array
    {
        $steps = [];

        foreach ($tickets as $ticket) {
            $ticket->loadMissing(['comments']);

            foreach ($ticket->comments
                ->where('is_internal', false)
                ->sortBy('created_at') as $comment) {
                $step = trim(preg_replace('/\s+/', ' ', $comment->body) ?? '');

                if ($step === '') {
                    continue;
                }

                $key = mb_strtolower($step);

                if (! isset($steps[$key])) {
                    $steps[$key] = $step;
                }

                if (count($steps) >= 6) {
                    break 2;
                }
            }
        }

        return array_values($steps);
    }

    private function newArticleBody(Ticket $ticket, Collection $sourceTickets, array $resolutionSteps): string
    {
        $symptoms = collect($sourceTickets)
            ->map(fn (Ticket $source): string => trim($source->subject))
            ->filter()
            ->unique()
            ->take(5)
            ->map(fn (string $subject): string => '<li>'.e($subject).'</li>')
            ->implode('');

        return '<h2>Overview</h2>'
            .'<p>'.e($ticket->description).'</p>'
            .' <h2>Common symptoms</h2><ul>'.$symptoms.'</ul>'
            .' <h2>Resolution steps</h2><ol>'
            .implode('', array_map(
                fn (string $step): string => '<li>'.e($step).'</li>',
                $resolutionSteps,
            ))
            .'</ol>'
            .' <h2>Before contacting support</h2>'
            .'<p>Complete the resolution steps above and record any remaining error message, screen, or affected account before reopening or creating a ticket.</p>';
    }

    private function articleTitle(string $subject): string
    {
        $title = trim(preg_replace('/\s+/', ' ', $subject) ?? '');
        $title = mb_ucfirst(mb_strtolower($title));

        if (mb_strlen($title) > 180) {
            $title = mb_substr($title, 0, 177).'...';
        }

        return $title !== '' ? $title : 'Support troubleshooting guide';
    }

    private function summary(Ticket $ticket, array $resolutionSteps): string
    {
        $summary = 'Troubleshooting guidance generated from similar resolved tickets.';

        if ($resolutionSteps !== []) {
            $summary = 'Learn the common symptoms and steps used to resolve this issue: '.mb_substr($resolutionSteps[0], 0, 180);
        }

        return mb_strlen($summary) > 500 ? mb_substr($summary, 0, 497).'...' : $summary;
    }

    private function terms(string $text): array
    {
        $tokens = preg_split('/[^\pL\pN]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_filter(
            $tokens ?: [],
            fn (string $term): bool => mb_strlen($term) > 2
                && ! in_array($term, self::STOP_WORDS, true),
        )));
    }
}
