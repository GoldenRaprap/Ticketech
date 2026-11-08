<?php

use App\Http\Middleware\ApplySystemMailSettings;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureKnowledgeBaseIsEnabled;
use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\EnsureTicketRepliesAreEnabled;
use App\Http\Middleware\EnsureTicketSubmissionIsOpen;
use App\Http\Middleware\RecordStaffActivity;
use App\Http\Middleware\RedirectWorkspaceUsers;
use App\Models\KnowledgeArticle;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', ApplySystemMailSettings::class);
        $middleware->alias([
            'staff' => EnsureStaff::class,
            'admin' => EnsureAdmin::class,
            'redirect.workspace-users' => RedirectWorkspaceUsers::class,
            'audit.activity' => RecordStaffActivity::class,
            'ticket-submission-open' => EnsureTicketSubmissionIsOpen::class,
            'knowledge-base-enabled' => EnsureKnowledgeBaseIsEnabled::class,
            'ticket-replies-enabled' => EnsureTicketRepliesAreEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            if (! $request->routeIs('knowledge-base.reviews.store') || $request->expectsJson()) {
                return null;
            }

            $article = $request->route('article');
            $articleSlug = $article instanceof KnowledgeArticle ? $article->slug : $article;

            if (! is_string($articleSlug) || $articleSlug === '') {
                return null;
            }

            $retryAfter = max(1, (int) ($exception->getHeaders()['Retry-After'] ?? 60));

            return to_route('knowledge-base.show', $articleSlug)
                ->with('review_rate_limited', $retryAfter);
        });
    })->create();
