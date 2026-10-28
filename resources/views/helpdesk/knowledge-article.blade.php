@extends('layouts.public')
@section('title', $article->title)
@section('content')
<main class="page-wrap help-scene public-article-scene"><div class="container public-article-page">
    @if (session('status'))
        <div class="workspace-alert workspace-alert--success public-article-status" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="workspace-alert workspace-alert--error public-article-status" role="alert">
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif
    <article class="public-article-detail">
        <span class="public-article-category">{{ $article->category }}</span>
        <h1>{{ $article->title }}</h1>
        <p class="public-article-summary">{{ $article->summary }}</p>
        <div class="public-article-byline">PCU Helpdesk · Updated {{ $article->published_at?->format('F j, Y') }}</div>
        <div class="public-article-body">{{ $article->body }}</div>
        @if ($article->images->isNotEmpty())
            <section class="public-article-images" aria-label="Article images">
                @foreach ($article->images as $image)
                    <figure>
                        <img src="{{ $image->url() }}" alt="Illustration {{ $loop->iteration }} for {{ $article->title }}" loading="lazy">
                    </figure>
                @endforeach
            </section>
        @endif
    </article>

    <section class="public-article-reviews" aria-labelledby="article-reviews-heading">
        <header class="public-review-heading">
            <div>
                <span class="eyebrow">COMMUNITY FEEDBACK</span>
                <h2 id="article-reviews-heading">Rate this article</h2>
                <p>Your rating is anonymous. No account or personal details are requested.</p>
            </div>
            @if ($reviewsVisible)
                <div class="public-review-average" aria-label="Average rating {{ number_format($averageRating, 1) }} out of 5">
                    <strong>{{ number_format($averageRating, 1) }}</strong>
                    <span class="public-review-average-stars" aria-hidden="true">
                        <span>★★★★★</span>
                        <span style="width: {{ number_format(($averageRating / 5) * 100, 1) }}%">★★★★★</span>
                    </span>
                </div>
            @endif
        </header>

        <form class="public-review-form" action="{{ route('knowledge-base.reviews.store', $article->slug) }}" method="post">
            @csrf
            <fieldset>
                <legend>Select a star rating</legend>
                <div class="public-review-choices">
                    @foreach (range(1, 5) as $rating)
                        <label class="public-review-choice" data-star-value="{{ $rating }}">
                            <input type="radio" name="rating" value="{{ $rating }}" aria-label="{{ $rating }} {{ $rating === 1 ? 'star' : 'stars' }}" @checked(old('rating') == $rating) required>
                            <span aria-hidden="true">★</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <button class="workspace-button workspace-button--primary" type="submit">Submit rating</button>
        </form>
    </section>

    <div class="public-article-footer"><span>Still need help?</span><a class="workspace-button workspace-button--primary" href="{{ route('ticket.create') }}">Submit a ticket</a></div>
</div></main>
@endsection
