<?php

namespace App\Http\Middleware;

use App\Services\PublicFeatureAvailability;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTicketRepliesAreEnabled
{
    public function __construct(private PublicFeatureAvailability $availability) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->availability->ticketRepliesAreEnabled()) {
            return to_route('ticket.check')
                ->with('ticket_reply_unavailable', $this->availability->ticketRepliesMessage());
        }

        return $next($request);
    }
}
