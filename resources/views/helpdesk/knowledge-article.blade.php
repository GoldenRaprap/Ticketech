@extends('layouts.public')
@section('title', $article->title)
@section('content')
<main class="page-wrap"><div class="container public-article-page">
    <nav class="ticket-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('knowledge-base') }}">Knowledge Base</a><span aria-hidden="true">/</span><span>{{ $article->category }}</span></nav>
    <article class="public-article-detail">
        <span class="public-article-category">{{ $article->category }}</span>
        <h1>{{ $article->title }}</h1>
        <p class="public-article-summary">{{ $article->summary }}</p>
        <div class="public-article-byline">PCU Helpdesk · Updated {{ $article->published_at?->format('F j, Y') }}</div>
        <div class="public-article-body">{{ $article->body }}</div>
    </article>
    <div class="public-article-footer"><span>Still need help?</span><a class="workspace-button workspace-button--primary" href="{{ route('ticket.create') }}">Submit a ticket</a></div>
</div></main>
@endsection
