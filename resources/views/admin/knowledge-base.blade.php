@extends('layouts.staff')
@section('title', 'Knowledge Base')
@section('content')
<div class="workspace-page knowledge-page">
    @if (session('status'))
        <div class="workspace-alert workspace-alert--success" role="status">{{ session('status') }}</div>
    @endif
    <header class="workspace-page-heading">
        <div>
            <span class="workspace-eyebrow">Self-service resources</span>
            <h1>Knowledge base</h1>
            <p>Create and maintain useful answers for PCU students and employees.</p>
        </div>
        <a class="workspace-button workspace-button--primary" href="{{ route('admin.knowledge-base.create') }}"><span aria-hidden="true">＋</span> New article</a>
    </header>

    <section class="knowledge-intro">
        <div class="knowledge-intro-icon" aria-hidden="true">✧</div>
        <div><strong>Help people help themselves</strong><p>Publish clear articles to make common support answers easy to find.</p></div>
        <span class="knowledge-article-count"><strong>{{ $articles->total() }}</strong> articles</span>
    </section>

    <section class="knowledge-review-settings">
        <div>
            <span class="workspace-eyebrow">ANONYMOUS ARTICLE RATINGS</span>
            <h2>Review visibility</h2>
            <p>Ratings stay hidden from visitors until an article receives the minimum number below.</p>
        </div>
        <form action="{{ route('admin.knowledge-base.review-settings') }}" method="post">
            @csrf
            @method('put')
            <label for="review-threshold">Minimum ratings</label>
            <input id="review-threshold" name="review_threshold" type="number" min="1" max="100" value="{{ old('review_threshold', $reviewThreshold) }}" required>
            <button class="workspace-button workspace-button--secondary" type="submit">Save setting</button>
            @error('review_threshold')
                <span class="knowledge-setting-error" role="alert">{{ $message }}</span>
            @enderror
        </form>
    </section>

    <section class="workspace-card knowledge-library">
        <div class="workspace-card-heading">
            <div><h2>Articles</h2><p>Draft and published resources.</p></div>
        </div>
        <form class="knowledge-toolbar" action="{{ route('admin.knowledge-base') }}" method="get">
            <label class="ticket-search-field"><span aria-hidden="true">⌕</span><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search articles" aria-label="Search articles"></label>
            <label><span class="sr-only">Filter by category</span><select name="category" aria-label="Filter article category">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
                @endforeach
            </select></label>
            <button class="workspace-button workspace-button--secondary" type="submit">Search</button>
            <span class="published-indicator"><span aria-hidden="true"></span> Public resources</span>
        </form>
        <div class="knowledge-article-list">
            @forelse ($articles as $article)
                <article class="knowledge-article">
                    @if ($article->images->isNotEmpty())
                        <img class="article-icon article-icon--image" src="{{ $article->images->first()->url() }}" alt="" loading="lazy">
                    @else
                        <span class="article-icon" aria-hidden="true">▤</span>
                    @endif
                    <div class="knowledge-article-copy">
                        <div class="knowledge-article-title"><h3>{{ $article->title }}</h3><span>{{ $article->category }}</span></div>
                        <p>{{ $article->summary }}</p>
                        <div class="knowledge-article-meta">
                            <span>Updated {{ $article->updated_at->format('M j, Y') }}</span>
                            <span class="{{ $article->is_published ? 'visibility-label' : 'draft-label' }}">{{ $article->is_published ? 'Published' : 'Draft' }}</span>
                            <span>By {{ $article->author?->name ?? 'Former staff member' }}</span>
                            <span>{{ $article->images->count() }} images</span>
                            <span>{{ $article->reviews_count }} ratings</span>
                        </div>
                    </div>
                    <div class="knowledge-article-actions">
                        <a class="workspace-button workspace-button--secondary article-edit-button" href="{{ route('admin.knowledge-base.edit', $article) }}">Edit</a>
                        <form action="{{ route('admin.knowledge-base.destroy', $article) }}" method="post" onsubmit="return confirm('Delete this article? This cannot be undone.')">
                            @csrf
                            @method('delete')
                            <button class="article-delete-button" type="submit">Delete</button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="workspace-empty-state"><span class="workspace-empty-icon" aria-hidden="true">▤</span><strong>No articles found</strong><p>Create an article or adjust your search.</p></div>
            @endforelse
        </div>
        <footer class="table-footer"><span>Showing {{ $articles->count() }} of {{ $articles->total() }} articles</span>{{ $articles->links() }}</footer>
    </section>
</div>
@endsection
