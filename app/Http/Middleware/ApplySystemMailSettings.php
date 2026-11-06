<?php

namespace App\Http\Middleware;

use App\Exceptions\SystemMailSettingsUnavailable;
use App\Services\SystemMailSettings;
use App\Services\TicketEmailAvailability;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplySystemMailSettings
{
    public function __construct(
        private SystemMailSettings $mailSettings,
        private TicketEmailAvailability $ticketEmailAvailability,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $this->mailSettings->apply();
        } catch (SystemMailSettingsUnavailable $exception) {
            $this->ticketEmailAvailability->disableTicketEmailFeatures($exception);
        }

        return $next($request);
    }
}
