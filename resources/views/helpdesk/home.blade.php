@extends('layouts.public')
@section('title', 'Home')
@section('content')
<section class="help-scene">
    <div class="scene-shapes" aria-hidden="true">
        <span class="shape shape-one"></span>
        <span class="shape shape-two"></span>
        <span class="shape shape-three"></span>
        <span class="shape shape-four"></span>
        <span class="shape shape-five"></span>
        <span class="shape shape-six"></span>
    </div>

    <div class="help-panel">
        <p class="home-eyebrow">PHILIPPINE CHRISTIAN UNIVERSITY</p>
        <h1>How can we help you today?</h1>
        <p class="home-intro">Get support for your classes, campus services, and university technology.</p>
        @auth
            <p class="home-welcome">Welcome back, {{ auth()->user()->name }}.</p>
        @endauth

        <div class="help-grid">
            <a class="option-card" href="{{ route('ticket.create') }}">
                <span class="option-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.5 3.5h9a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2h-9a2 2 0 0 1-2-2v-13a2 2 0 0 1 2-2zm2 5h5m-5 4h5m-6 4h3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span class="option-copy">
                    <strong>Submit a Ticket</strong>
                    <small>Tell us what you need and we’ll route it to the right team.</small>
                </span>
            </a>

            <a class="option-card" href="{{ route('ticket.check') }}">
                <span class="option-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.5 4.5h9a2 2 0 0 1 2 2v11.5a2 2 0 0 1-2 2h-9a2 2 0 0 1-2-2V6.5a2 2 0 0 1 2-2zm1.5 7h5m-5 4h5M9 3v3m6-3v3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span class="option-copy">
                    <strong>View Your Tickets</strong>
                    <small>Check request status and read the latest support updates.</small>
                </span>
            </a>
        </div>

        <p class="home-note">
            No account needed. Submit a request or check its status using your ticket number and email.
            @auth
                @if (auth()->user()->isStaff())
                    Staff sign-in is shared across this browser's tabs. Closing one tab will not sign out the others; use a separate browser profile or private window for another staff member.
                @endif
            @endauth
        </p>

        @auth
            @if (auth()->user()->isAdmin())
                <a class="admin-link" href="{{ route('admin.dashboard') }}">Open Admin Console</a>
            @elseif (auth()->user()->isStaff())
                <a class="admin-link" href="{{ route('staff.dashboard') }}">Open Staff Workspace</a>
            @endif
        @endauth

        @if ($recentTickets->isNotEmpty())
            <section class="home-recent-tickets" aria-label="Recent requests">
                <h3>Recent requests</h3>
                @foreach ($recentTickets as $ticket)
                    <div class="home-recent-ticket">
                        <strong>{{ $ticket->ticket_number }}</strong>
                        <span>{{ $ticket->subject }}</span>
                        <span>{{ $ticket->status }}</span>
                    </div>
                @endforeach
            </section>
        @endif
    </div>

    <section class="home-knowledge-panel" aria-labelledby="home-knowledge-heading">
        <div class="home-knowledge-section">
            <header class="home-knowledge-heading">
                <span class="home-knowledge-eyebrow">SELF-SERVICE SUPPORT</span>
                <h2 id="home-knowledge-heading">Helpful Articles</h2>
                <p>Browse helpful guides and answers from the PCU Helpdesk team.</p>
            </header>

            <div class="public-article-grid home-knowledge-grid">
                @forelse ($featuredArticles as $article)
                    <article class="public-article-card">
                        @if ($article->images->isNotEmpty())
                            <a class="public-article-card-image" href="{{ route('knowledge-base.show', $article->slug) }}" tabindex="-1" aria-hidden="true">
                                <img src="{{ $article->images->first()->url() }}" alt="" loading="lazy">
                            </a>
                        @endif
                        <div class="public-article-card-copy">
                            <span class="public-article-category">{{ $article->category }}</span>
                            <h3><a href="{{ route('knowledge-base.show', $article->slug) }}">{{ $article->title }}</a></h3>
                            <p>{{ $article->summary }}</p>
                            <div class="public-article-card-footer">
                                @include('helpdesk.partials.article-rating', ['article' => $article, 'reviewThreshold' => $reviewThreshold])
                                <a class="public-article-read-link" href="{{ route('knowledge-base.show', $article->slug) }}">Read article <span aria-hidden="true">→</span></a>
                            </div>
                        </div>
                    </article>
                @empty
                    <p class="home-knowledge-empty">There are no published help articles yet.</p>
                @endforelse
            </div>

            <a class="home-knowledge-all" href="{{ route('knowledge-base') }}">Browse all articles <span aria-hidden="true">→</span></a>
        </div>
    </section>
</section>
@endsection