<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeArticle;
use App\Services\KnowledgeArticleReviewSettings;
use App\Services\PublicFeatureAvailability;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(
        Request $request,
        KnowledgeArticleReviewSettings $reviewSettings,
        PublicFeatureAvailability $availability,
    ): View {
        $recentTickets = $request->user()?->tickets()->latest()->limit(3)->get() ?? collect();
        $knowledgeBaseEnabled = $availability->knowledgeBaseIsEnabled();
        $featuredArticles = $knowledgeBaseEnabled
            ? KnowledgeArticle::query()
                ->with('images')
                ->withCount('reviews')
                ->withAvg('reviews', 'rating')
                ->where('is_published', true)
                ->orderByDesc('published_at')
                ->limit(3)
                ->get()
            : collect();

        return view('helpdesk.home', [
            'featuredArticles' => $featuredArticles,
            'knowledgeBaseEnabled' => $knowledgeBaseEnabled,
            'knowledgeBaseMessage' => $availability->knowledgeBaseMessage(),
            'recentTickets' => $recentTickets,
            'reviewThreshold' => $reviewSettings->visibilityThreshold(),
        ]);
    }
}
