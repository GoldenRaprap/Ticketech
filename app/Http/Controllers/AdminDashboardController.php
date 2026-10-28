<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        $openStatuses = ['New', 'Assigned', 'In Progress', 'Waiting for User', 'On Hold'];

        return view('admin.dashboard', [
            'ticketCount' => Ticket::query()->count(),
            'openTicketCount' => Ticket::query()->whereIn('status', $openStatuses)->count(),
            'staffCount' => User::query()->where('role', 'staff')->where('employment_status', 'active')->count(),
            'requesterCount' => User::query()->where('role', 'requester')->count(),
        ]);
    }
}
