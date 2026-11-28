<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKnowledgeArticleRequest;
use App\Http\Requests\UpdateKnowledgeArticleRequest;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeArticleSuggestion;
use App\Models\Ticket;
use App\Services\AuditActivityLogger;
use App\Services\KnowledgeArticleContentFormatter;
use App\Services\KnowledgeArticleReviewSettings;
use App\Services\KnowledgeArticleSuggestionGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class KnowledgeArticleController extends Controller
{
    private const RECOMMENDATION_STOP_WORDS = [
        'and', 'are', 'but', 'can', 'cannot', 'for', 'from', 'have', 'help',
        'into', 'issue', 'issues', 'need', 'please', 'problem', 'that', 'the',
        'this', 'with', 'your',
    ];

    public function publicIndex(Request $request, KnowledgeArticleReviewSettings $reviewSettings): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $articles = KnowledgeArticle::query()
            ->with('images')
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->where('is_published', true)
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', '%'.$search.'%')
                        ->orWhere('summary', 'like', '%'.$search.'%')
                        ->orWhere('body', 'like', '%'.$search.'%');
                });
            })
            ->orderByDesc('published_at')
            ->paginate(9)
            ->withQueryString();

        return view('helpdesk.knowledge-base', [
            'articles' => $articles,
            'filters' => $filters,
            'reviewThreshold' => $reviewSettings->visibilityThreshold(),
        ]);
    }

    public function recommendations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:150'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);
        $terms = array_slice(
            $this->recommendationTerms(($validated['subject'] ?? '').' '.($validated['message'] ?? '')),
            0,
            10,
        );

        if (count($terms) < 2) {
            return response()->json(['articles' => []]);
        }

        $articles = KnowledgeArticle::query()
            ->where('is_published', true)
            ->where(function ($query) use ($terms): void {
                foreach ($terms as $term) {
                    $query->orWhere('title', 'like', '%'.$term.'%')
                        ->orWhere('summary', 'like', '%'.$term.'%')
                        ->orWhere('body', 'like', '%'.$term.'%');
                }
            })
            ->limit(100)
            ->get(['id', 'title', 'slug', 'category', 'summary', 'body'])
            ->map(function (KnowledgeArticle $article) use ($terms): array {
                $articleTerms = $this->recommendationTerms(
                    $article->title.' '.$article->category.' '.$article->summary.' '.strip_tags($article->body),
                );
                $matchedTerms = array_intersect($terms, $articleTerms);

                return [
                    'article' => $article,
                    'match_count' => count($matchedTerms),
                    'match_ratio' => count($matchedTerms) / count($terms),
                ];
            })
            ->filter(fn (array $match): bool => $match['match_count'] >= 2)
            ->sortByDesc('match_ratio')
            ->take(3)
            ->map(fn (array $match): array => [
                'title' => $match['article']->title,
                'category' => $match['article']->category,
                'summary' => $match['article']->summary,
                'url' => route('knowledge-base.show', $match['article']->slug),
            ])
            ->values();

        return response()->json(['articles' => $articles]);
    }

    public function publicShow(
        KnowledgeArticle $article,
        KnowledgeArticleReviewSettings $reviewSettings,
        KnowledgeArticleContentFormatter $contentFormatter,
    ): View {
        abort_unless($article->is_published, 404);

        $article->load('images');
        $reviewStats = $article->reviews()
            ->selectRaw('COUNT(*) as review_count, AVG(rating) as average_rating')
            ->firstOrFail();
        $reviewThreshold = $reviewSettings->visibilityThreshold();
        $reviewsVisible = (int) $reviewStats->review_count >= $reviewThreshold;
        $averageRating = $reviewsVisible ? round((float) $reviewStats->average_rating, 1) : null;

        return view('helpdesk.knowledge-article', [
            'article' => $article,
            'articleContent' => $contentFormatter->format($article->body),
            'averageRating' => $averageRating,
            'reviewsVisible' => $reviewsVisible,
        ]);
    }

    public function index(Request $request, KnowledgeArticleReviewSettings $reviewSettings): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:80'],
        ]);

        $articles = KnowledgeArticle::query()
            ->with(['author:id,name', 'images'])
            ->withCount('reviews')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', '%'.$search.'%')
                        ->orWhere('summary', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        return view('admin.knowledge-base', [
            'articles' => $articles,
            'filters' => $filters,
            'categories' => KnowledgeArticle::query()->distinct()->orderBy('category')->pluck('category'),
            'reviewThreshold' => $reviewSettings->visibilityThreshold(),
            'suggestions' => KnowledgeArticleSuggestion::query()
                ->with('targetArticle:id,title')
                ->where('status', 'pending')
                ->latest()
                ->get(),
        ]);
    }

    public function generateSuggestions(
        KnowledgeArticleSuggestionGenerator $generator,
    ): RedirectResponse {
        $created = 0;

        Ticket::query()
            ->whereIn('status', ['Resolved', 'Closed'])
            ->latest('id')
            ->limit(200)
            ->get()
            ->each(function (Ticket $ticket) use ($generator, &$created): void {
                $suggestion = $generator->generateForTicket($ticket);

                if ($suggestion?->wasRecentlyCreated) {
                    $created++;
                }
            });

        return to_route('admin.knowledge-base')
            ->with('status', $created === 0
                ? 'No new article suggestions were found.'
                : "{$created} article suggestion".($created === 1 ? ' was' : 's were').' generated.');
    }

    public function approveSuggestion(
        Request $request,
        KnowledgeArticleSuggestion $suggestion,
        KnowledgeArticleContentFormatter $contentFormatter,
        AuditActivityLogger $auditLogger,
    ): RedirectResponse {
        abort_unless($suggestion->status === 'pending', 404);

        DB::transaction(function () use ($request, $suggestion, $contentFormatter, $auditLogger): void {
            if ($suggestion->target_article_id !== null) {
                $article = KnowledgeArticle::query()->lockForUpdate()->findOrFail($suggestion->target_article_id);
                $article->update([
                    'draft_title' => $article->draft_title ?? $suggestion->title,
                    'draft_category' => $article->draft_category ?? $suggestion->category,
                    'draft_summary' => $article->draft_summary ?? $article->summary,
                    'draft_body' => $this->appendArticleUpdate(
                        $article->draft_body ?? $article->body,
                        $article,
                        $suggestion,
                        $contentFormatter,
                    ),
                    'draft_generated_at' => now(),
                ]);
            } else {
                KnowledgeArticle::create([
                    'author_id' => $request->user()->id,
                    'title' => $suggestion->title,
                    'slug' => $this->makeSlug($suggestion->title),
                    'category' => $suggestion->category,
                    'summary' => $suggestion->summary,
                    'body' => $contentFormatter->format($suggestion->proposed_body),
                    'is_published' => false,
                    'published_at' => null,
                ]);
            }

            $suggestion->update([
                'status' => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            $auditLogger->record(
                $request,
                'knowledge_article_suggestion_approved',
                "Approved an automated knowledge-base suggestion: {$suggestion->title}.",
                $suggestion->targetArticle,
                ['suggestion_id' => $suggestion->id],
            );
        });

        return to_route('admin.knowledge-base')->with(
            'status',
            $suggestion->target_article_id !== null
                ? 'The update was added to the existing article as a draft.'
                : 'The generated article was created as a draft.',
        );
    }

    private function appendArticleUpdate(
        string $currentBody,
        KnowledgeArticle $article,
        KnowledgeArticleSuggestion $suggestion,
        KnowledgeArticleContentFormatter $contentFormatter,
    ): string {
        $formattedCurrentBody = $contentFormatter->format($currentBody);
        $proposedBody = $contentFormatter->format($suggestion->proposed_body);
        $newUpdateHeading = '<h2>New information from resolved ticket:';

        if (! str_contains($proposedBody, $newUpdateHeading)) {
            $publishedBody = $contentFormatter->format($article->body);

            if ($publishedBody !== '' && str_starts_with($proposedBody, $publishedBody)) {
                $proposedBody = $contentFormatter->format(trim(substr($proposedBody, strlen($publishedBody))));
            } else {
                $legacyHeading = '<h2>Additional concerns identified from recent tickets</h2>';
                $legacyHeadingPosition = strpos($proposedBody, $legacyHeading);

                if ($legacyHeadingPosition !== false) {
                    $proposedBody = substr($proposedBody, $legacyHeadingPosition);
                }

            }
        }

        if ($proposedBody !== '' && ! str_contains($formattedCurrentBody, $proposedBody)) {
            return trim($formattedCurrentBody."\n".$proposedBody);
        }

        return $formattedCurrentBody;
    }

    /**
     * @return list<string>
     */
    private function recommendationTerms(string $content): array
    {
        $tokens = preg_split('/[^\pL\pN]+/u', mb_strtolower($content), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_filter(
            $tokens ?: [],
            fn (string $term): bool => mb_strlen($term) > 2
                && ! in_array($term, self::RECOMMENDATION_STOP_WORDS, true),
        )));
    }

    public function dismissSuggestion(
        Request $request,
        KnowledgeArticleSuggestion $suggestion,
        AuditActivityLogger $auditLogger,
    ): RedirectResponse {
        abort_unless($suggestion->status === 'pending', 404);

        $suggestion->update([
            'status' => 'dismissed',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $auditLogger->record(
            $request,
            'knowledge_article_suggestion_dismissed',
            "Dismissed an automated knowledge-base suggestion: {$suggestion->title}.",
        );

        return to_route('admin.knowledge-base')->with('status', 'Article suggestion dismissed.');
    }

    public function create(Request $request, KnowledgeArticleContentFormatter $contentFormatter): View
    {
        $oldBody = $request->session()->getOldInput('body', '');

        return view('admin.knowledge-article-form', [
            'article' => new KnowledgeArticle,
            'articleContent' => $contentFormatter->format(is_string($oldBody) ? $oldBody : ''),
        ]);
    }

    public function updateReviewSettings(Request $request, AuditActivityLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'review_threshold' => ['required', 'integer', 'between:1,100'],
        ]);
        $reviewThreshold = (int) $validated['review_threshold'];

        DB::table('system_settings')->updateOrInsert(
            ['setting' => 'knowledge_base.review_visibility_threshold'],
            ['value' => (string) $reviewThreshold, 'created_at' => now(), 'updated_at' => now()],
        );
        $auditLogger->record(
            $request,
            'knowledge_base_review_settings_updated',
            "Set the knowledge-base review visibility threshold to {$reviewThreshold}.",
            properties: ['review_visibility_threshold' => $reviewThreshold],
        );

        return to_route('admin.knowledge-base')->with('status', 'Review visibility settings updated.');
    }

    public function store(
        StoreKnowledgeArticleRequest $request,
        AuditActivityLogger $auditLogger,
        KnowledgeArticleContentFormatter $contentFormatter,
    ): RedirectResponse {
        $data = $request->validated();
        $uploadedImages = $data['images'] ?? [];
        unset($data['images']);
        $isPublished = (bool) ($data['is_published'] ?? false);
        $storedImagePaths = [];

        try {
            $article = DB::transaction(function () use (
                $request,
                $data,
                $uploadedImages,
                $isPublished,
                $contentFormatter,
                &$storedImagePaths,
            ): KnowledgeArticle {
                $article = KnowledgeArticle::create([
                    'author_id' => $request->user()->id,
                    'title' => $data['title'],
                    'slug' => $this->makeSlug($data['title']),
                    'category' => $data['category'],
                    'summary' => $data['summary'],
                    'body' => $contentFormatter->format($data['body']),
                    'is_published' => $isPublished,
                    'published_at' => $isPublished ? now() : null,
                ]);
                $this->storeImages($article, $uploadedImages, $storedImagePaths);

                return $article;
            });
        } catch (Throwable $exception) {
            $this->cleanupStoredImages($storedImagePaths);

            throw $exception;
        }

        $auditLogger->record(
            $request,
            'knowledge_article_created',
            "Created knowledge article {$article->title}.",
            $article,
            [
                'after' => [
                    'title' => $article->title,
                    'category' => $article->category,
                    'is_published' => $article->is_published,
                ],
            ],
        );

        return to_route('admin.knowledge-base.edit', $article)->with('status', 'Article created successfully.');
    }

    public function edit(
        Request $request,
        KnowledgeArticle $article,
        KnowledgeArticleContentFormatter $contentFormatter,
    ): View {
        $oldBody = $request->session()->getOldInput(
            'body',
            $article->draft_body ?? $article->body,
        );

        if ($article->draft_title !== null) {
            $article->title = $article->draft_title;
            $article->category = $article->draft_category ?? $article->category;
            $article->summary = $article->draft_summary ?? $article->summary;
        }

        return view('admin.knowledge-article-form', [
            'article' => $article,
            'articleContent' => $contentFormatter->format(is_string($oldBody) ? $oldBody : ''),
        ]);
    }

    public function update(
        UpdateKnowledgeArticleRequest $request,
        KnowledgeArticle $article,
        AuditActivityLogger $auditLogger,
        KnowledgeArticleContentFormatter $contentFormatter,
    ): RedirectResponse {
        $previousDetails = [
            'title' => $article->title,
            'category' => $article->category,
            'is_published' => $article->is_published,
        ];
        $data = $request->validated();
        $uploadedImages = $data['images'] ?? [];
        $imagesToRemove = $data['remove_images'] ?? [];
        unset($data['images'], $data['remove_images']);
        $isPublished = (bool) ($data['is_published'] ?? false);
        $wasPublished = $article->is_published;
        $storedImagePaths = [];
        $removedImagePaths = [];

        try {
            DB::transaction(function () use (
                $article,
                $data,
                $isPublished,
                $uploadedImages,
                $imagesToRemove,
                $contentFormatter,
                &$storedImagePaths,
                &$removedImagePaths,
            ): void {
                $article->fill([
                    'title' => $data['title'],
                    'category' => $data['category'],
                    'summary' => $data['summary'],
                    'body' => $contentFormatter->format($data['body']),
                    'is_published' => $isPublished,
                    'published_at' => $isPublished ? ($article->published_at ?? now()) : null,
                    'draft_title' => null,
                    'draft_category' => null,
                    'draft_summary' => null,
                    'draft_body' => null,
                    'draft_generated_at' => null,
                ]);

                if ($article->isDirty('title')) {
                    $article->slug = $this->makeSlug($data['title']);
                }

                $article->save();

                foreach ($article->images()->whereKey($imagesToRemove)->get() as $image) {
                    $removedImagePaths[] = $image->path;
                    $image->delete();
                }

                $this->storeImages($article, $uploadedImages, $storedImagePaths);
            });
        } catch (Throwable $exception) {
            $this->cleanupStoredImages($storedImagePaths);

            throw $exception;
        }

        $this->removeStoredImages($removedImagePaths);
        $auditLogger->record(
            $request,
            'knowledge_article_updated',
            "Updated knowledge article {$article->title}.",
            $article,
            [
                'before' => $previousDetails,
                'after' => [
                    'title' => $article->title,
                    'category' => $article->category,
                    'is_published' => $article->is_published,
                ],
            ],
        );

        $message = $wasPublished === $isPublished
            ? 'Article updated successfully.'
            : ($isPublished ? 'Article published successfully.' : 'Article saved as a draft.');

        return to_route('admin.knowledge-base')->with('status', $message);
    }

    public function destroy(
        Request $request,
        KnowledgeArticle $article,
        AuditActivityLogger $auditLogger,
    ): RedirectResponse {
        $title = $article->title;
        $imagePaths = $article->images()->pluck('path')->all();
        $previousDetails = [
            'title' => $article->title,
            'category' => $article->category,
            'is_published' => $article->is_published,
        ];
        $article->delete();
        $this->removeStoredImages($imagePaths);
        $auditLogger->record(
            $request,
            'knowledge_article_deleted',
            "Deleted knowledge article {$title}.",
            $article,
            ['before' => $previousDetails],
        );

        return to_route('admin.knowledge-base')->with('status', 'Article deleted.');
    }

    public function storeReview(Request $request, KnowledgeArticle $article): RedirectResponse
    {
        abort_unless($article->is_published, 404);

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
        ]);
        $article->reviews()->create(['rating' => (int) $validated['rating']]);

        return to_route('knowledge-base.show', $article->slug)
            ->with('status', 'Thank you for your anonymous rating.');
    }

    /**
     * @param  list<UploadedFile>  $images
     * @param  list<string>  $storedPaths
     */
    private function storeImages(KnowledgeArticle $article, array $images, array &$storedPaths): void
    {
        foreach ($images as $image) {
            $path = $image->storePublicly("knowledge-base/{$article->id}", 'public');

            if ($path === false) {
                throw new RuntimeException('Unable to store a knowledge-base image.');
            }

            $storedPaths[] = $path;
            $article->images()->create(['path' => $path]);
        }
    }

    /**
     * @param  list<string>  $paths
     */
    private function removeStoredImages(array $paths): void
    {
        if ($paths !== [] && ! Storage::disk('public')->delete($paths)) {
            throw new RuntimeException('Unable to remove one or more knowledge-base images.');
        }
    }

    /**
     * @param  list<string>  $paths
     */
    private function cleanupStoredImages(array $paths): void
    {
        try {
            $this->removeStoredImages($paths);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function makeSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'article';
        $slug = $base;
        $suffix = 2;

        while (DB::table('knowledge_articles')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
