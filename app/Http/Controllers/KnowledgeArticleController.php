<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKnowledgeArticleRequest;
use App\Http\Requests\UpdateKnowledgeArticleRequest;
use App\Models\KnowledgeArticle;
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

    public function store(StoreKnowledgeArticleRequest $request): RedirectResponse
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

        return to_route('staff.knowledge-base.edit', $article)->with('status', 'Article created successfully.');
    }

    public function edit(KnowledgeArticle $article): View
    {
        return view('staff.knowledge-article-form', compact('article'));
    }

    public function update(UpdateKnowledgeArticleRequest $request, KnowledgeArticle $article): RedirectResponse
    {
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

        $message = $wasPublished === $isPublished
            ? 'Article updated successfully.'
            : ($isPublished ? 'Article published successfully.' : 'Article saved as a draft.');

        return to_route('staff.knowledge-base')->with('status', $message);
    }

    public function destroy(KnowledgeArticle $article): RedirectResponse
    {
        $article->delete();

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
