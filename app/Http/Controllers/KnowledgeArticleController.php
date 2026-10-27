<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKnowledgeArticleRequest;
use App\Http\Requests\UpdateKnowledgeArticleRequest;
use App\Models\KnowledgeArticle;
use App\Services\AuditActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class KnowledgeArticleController extends Controller
{
    public function publicIndex(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $articles = KnowledgeArticle::query()
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

        return view('helpdesk.knowledge-base', compact('articles', 'filters'));
    }

    public function publicShow(KnowledgeArticle $article): View
    {
        abort_unless($article->is_published, 404);

        return view('helpdesk.knowledge-article', compact('article'));
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:80'],
        ]);

        $articles = KnowledgeArticle::query()
            ->with('author:id,name')
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

        return view('staff.knowledge-base', [
            'articles' => $articles,
            'filters' => $filters,
            'categories' => KnowledgeArticle::query()->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function create(): View
    {
        return view('staff.knowledge-article-form', ['article' => new KnowledgeArticle]);
    }

    public function store(StoreKnowledgeArticleRequest $request, AuditActivityLogger $auditLogger): RedirectResponse
    {
        $data = $request->validated();
        $isPublished = (bool) ($data['is_published'] ?? false);

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

        return to_route('staff.knowledge-base.edit', $article)->with('status', 'Article created successfully.');
    }

    public function edit(KnowledgeArticle $article): View
    {
        return view('staff.knowledge-article-form', compact('article'));
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
        $isPublished = (bool) ($data['is_published'] ?? false);
        $wasPublished = $article->is_published;

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

        return to_route('staff.knowledge-base')->with('status', $message);
    }

    public function destroy(
        Request $request,
        KnowledgeArticle $article,
        AuditActivityLogger $auditLogger,
    ): RedirectResponse {
        $title = $article->title;
        $previousDetails = [
            'title' => $article->title,
            'category' => $article->category,
            'is_published' => $article->is_published,
        ];
        $article->delete();
        $auditLogger->record(
            $request,
            'knowledge_article_deleted',
            "Deleted knowledge article {$title}.",
            $article,
            ['before' => $previousDetails],
        );

        return to_route('staff.knowledge-base')->with('status', 'Article deleted.');
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
