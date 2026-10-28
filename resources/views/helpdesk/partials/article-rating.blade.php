@if ($article->reviews_count >= $reviewThreshold)
    <div class="public-article-rating" aria-label="Average rating {{ number_format((float) $article->reviews_avg_rating, 1) }} out of 5">
        <strong>{{ number_format((float) $article->reviews_avg_rating, 1) }}</strong>
        <span class="public-review-average-stars" aria-hidden="true">
            <span>★★★★★</span>
            <span style="width: {{ number_format(((float) $article->reviews_avg_rating / 5) * 100, 1) }}%">★★★★★</span>
        </span>
    </div>
@endif
