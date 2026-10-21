<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaff
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        abort_unless($request->user()->isStaff(), Response::HTTP_FORBIDDEN);

        if (! $request->user()->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->guest(route('login'))->withErrors([
                'email' => 'This staff account is inactive. Contact an administrator for assistance.',
            ]);
        }

        return $next($request);
    }
}
