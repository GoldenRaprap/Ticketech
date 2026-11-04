<?php

namespace App\Http\Middleware;

use App\Services\PublicFeatureAvailability;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKnowledgeBaseIsEnabled
{
    public function __construct(private PublicFeatureAvailability $availability) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->availability->knowledgeBaseIsEnabled()) {
            return response()->view('helpdesk.feature-unavailable', [
                'featureName' => 'Knowledge base',
                'message' => $this->availability->knowledgeBaseMessage(),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $next($request);
    }
}
