<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user()?->isAdmin()) {
            return to_route('admin.dashboard');
        }

        if ($request->user()?->isStaff()) {
            return to_route('staff.dashboard');
        }

        $recentTickets = $request->user()?->tickets()->latest()->limit(3)->get() ?? collect();

        return view('helpdesk.home', ['recentTickets' => $recentTickets]);
    }
}
