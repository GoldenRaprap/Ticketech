<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeArticle;
use App\Services\KnowledgeArticleReviewSettings;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request, KnowledgeArticleReviewSettings $reviewSettings): View
    {
        $recentTickets = $request->user()?->tickets()->latest()->limit(3)->get() ?? collect();
        $featuredArticles = KnowledgeArticle::query()
            ->with('images')
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->where('is_published', true)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return view('helpdesk.home', [
            'featuredArticles' => $featuredArticles,
            'recentTickets' => $recentTickets,
            'reviewThreshold' => $reviewSettings->visibilityThreshold(),
        ]);
    }
}
