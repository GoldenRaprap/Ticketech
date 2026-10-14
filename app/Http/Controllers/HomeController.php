<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $recentTickets = $request->user()?->tickets()->latest()->limit(3)->get() ?? collect();

        return view('pages.home', ['recentTickets' => $recentTickets]);
    }
}
