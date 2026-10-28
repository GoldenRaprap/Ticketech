@extends('layouts.public')
@section('title', 'Helpful Articles')
@section('content')
<main class="page-wrap help-scene public-knowledge-scene"><div class="container public-knowledge-page">
    <header class="public-knowledge-heading">
        <span class="eyebrow">SELF-SERVICE SUPPORT</span>
        <h1 class="page-title">Helpful Articles</h1>
        <p class="page-intro">Clear guides and answers from the PCU Helpdesk team.</p>
    </header>
    <form class="public-knowledge-search" action="{{ route('knowledge-base') }}" method="get">
        <label class="ticket-search-field"><span aria-hidden="true">⌕</span><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search help articles" aria-label="Search help articles"></label>
        <button class="workspace-button workspace-button--primary" type="submit">Search</button>
    </form>
    <div class="public-article-grid">
        @forelse ($articles as $article)
            <article class="public-article-card">
                @if ($article->images->isNotEmpty())
                    <a class="public-article-card-image" href="{{ route('knowledge-base.show', $article->slug) }}" tabindex="-1" aria-hidden="true">
                        <img src="{{ $article->images->first()->url() }}" alt="" loading="lazy">
                    </a>
                @endif
                <div class="public-article-card-copy">
                    <span class="public-article-category">{{ $article->category }}</span>
                    <h2><a href="{{ route('knowledge-base.show', $article->slug) }}">{{ $article->title }}</a></h2>
                    <p>{{ $article->summary }}</p>
                    <div class="public-article-card-footer">
                        @include('helpdesk.partials.article-rating', ['article' => $article, 'reviewThreshold' => $reviewThreshold])
                        <a class="public-article-read-link" href="{{ route('knowledge-base.show', $article->slug) }}">Read article <span aria-hidden="true">→</span></a>
                    </div>
                </div>
            </article>
        @empty
            <div class="workspace-empty-state public-knowledge-empty"><span class="workspace-empty-icon" aria-hidden="true">▤</span><strong>No published articles found</strong><p>Try another search, or submit a ticket to the Helpdesk team.</p><a class="workspace-button workspace-button--primary" href="{{ route('ticket.create') }}">Submit a ticket</a></div>
        @endforelse
    </div>
    {{ $articles->links() }}
</div></main>
@endsection
