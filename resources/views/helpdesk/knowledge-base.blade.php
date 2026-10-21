@extends('layouts.public')
@section('title', 'Knowledge Base')
@section('content')
<main class="page-wrap"><div class="container public-knowledge-page">
    <span class="eyebrow">Self-service support</span><h1 class="page-title">Helpdesk Knowledge Base</h1>
    <p class="page-intro">Browse published guides and answers from the PCU Helpdesk team.</p>
    <form class="public-knowledge-search" action="{{ route('knowledge-base') }}" method="get">
        <label class="ticket-search-field"><span aria-hidden="true">⌕</span><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search help articles" aria-label="Search help articles"></label>
        <button class="workspace-button workspace-button--primary" type="submit">Search</button>
    </form>
    <div class="public-article-grid">
        @forelse ($articles as $article)
            <article class="public-article-card">
                <span class="public-article-category">{{ $article->category }}</span>
                <h2><a href="{{ route('knowledge-base.show', $article->slug) }}">{{ $article->title }}</a></h2>
                <p>{{ $article->summary }}</p>
                <a class="public-article-read-link" href="{{ route('knowledge-base.show', $article->slug) }}">Read article <span aria-hidden="true">→</span></a>
            </article>
        @empty
            <div class="workspace-empty-state public-knowledge-empty"><span class="workspace-empty-icon" aria-hidden="true">▤</span><strong>No published articles found</strong><p>Try another search, or submit a ticket to the Helpdesk team.</p><a class="workspace-button workspace-button--primary" href="{{ route('ticket.create') }}">Submit a ticket</a></div>
        @endforelse
    </div>
    {{ $articles->links() }}
</div></main>
@endsection
