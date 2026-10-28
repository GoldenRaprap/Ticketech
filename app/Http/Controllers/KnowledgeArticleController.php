<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKnowledgeArticleRequest;
use App\Http\Requests\UpdateKnowledgeArticleRequest;
use App\Models\KnowledgeArticle;
use App\Services\AuditActivityLogger;
use App\Services\KnowledgeArticleReviewSettings;
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

    public function publicShow(KnowledgeArticle $article, KnowledgeArticleReviewSettings $reviewSettings): View
    {
        abort_unless($article->is_published, 404);

        $article->load('images')->loadCount('reviews');
        $reviewThreshold = $reviewSettings->visibilityThreshold();
        $reviewsVisible = $article->reviews_count >= $reviewThreshold;
        $averageRating = $reviewsVisible ? round((float) $article->reviews()->avg('rating'), 1) : null;

        return view('helpdesk.knowledge-article', compact(
            'article',
            'averageRating',
            'reviewsVisible',
        ));
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
        ]);
    }

    public function create(): View
    {
        return view('admin.knowledge-article-form', ['article' => new KnowledgeArticle]);
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

    public function store(StoreKnowledgeArticleRequest $request, AuditActivityLogger $auditLogger): RedirectResponse
    {
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
                &$storedImagePaths,
            ): KnowledgeArticle {
                $article = KnowledgeArticle::create([
                    'author_id' => $request->user()->id,
                    'title' => $data['title'],
                    'slug' => $this->makeSlug($data['title']),
                    'category' => $data['category'],
                    'summary' => $data['summary'],
                    'body' => $data['body'],
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

    public function edit(KnowledgeArticle $article): View
    {
        return view('admin.knowledge-article-form', compact('article'));
    }

    public function update(
        UpdateKnowledgeArticleRequest $request,
        KnowledgeArticle $article,
        AuditActivityLogger $auditLogger,
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
                &$storedImagePaths,
                &$removedImagePaths,
            ): void {
                $article->fill([
                    'title' => $data['title'],
                    'category' => $data['category'],
                    'summary' => $data['summary'],
                    'body' => $data['body'],
                    'is_published' => $isPublished,
                    'published_at' => $isPublished ? ($article->published_at ?? now()) : null,
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
