<?php

namespace App\Http\Middleware;

use App\Services\TicketSubmissionSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTicketSubmissionIsOpen
{
    public function __construct(private TicketSubmissionSettings $submissionSettings) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->submissionSettings->isOpen()) {
            return to_route('ticket.create')
                ->with('ticket_submission_closed', $this->submissionSettings->closedMessage());
        }

        return $next($request);
    }
}
