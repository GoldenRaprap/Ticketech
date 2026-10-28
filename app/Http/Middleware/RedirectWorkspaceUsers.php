<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectWorkspaceUsers
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isAdmin()) {
            return to_route('admin.dashboard');
        }

        if ($request->user()?->isStaff()) {
            return to_route('staff.dashboard');
        }

        return $next($request);
    }
}
