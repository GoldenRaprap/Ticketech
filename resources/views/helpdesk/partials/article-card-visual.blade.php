@if ($article->images->isNotEmpty())
    <a class="public-article-card-image" href="{{ route('knowledge-base.show', $article->slug) }}" tabindex="-1" aria-hidden="true">
        <img src="{{ $article->images->first()->url() }}" alt="" loading="lazy">
    </a>
@else
    <div class="public-article-card-placeholder" aria-hidden="true">
        <span class="public-article-card-placeholder-copy">
            <span>PCU HELP CENTER</span>
            <strong>{{ $article->category }}</strong>
        </span>
        <svg viewBox="0 0 64 64" fill="none" aria-hidden="true">
            <path d="M32 20.5c-7.5-5.2-15-5.2-22.5-2v27c7.5-3.2 15-3.2 22.5 2m0-27c7.5-5.2 15-5.2 22.5-2v27c-7.5-3.2-15-3.2-22.5 2m0-27v27" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M15 25.5c4.2-1.1 8.3-.9 12.5.7m-12.5 5c4.2-1.1 8.3-.9 12.5.7m21-6.4c-4.2-1.1-8.3-.9-12.5.7m12.5 5c-4.2-1.1-8.3-.9-12.5.7" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity=".72"/>
        </svg>
    </div>
@endif
