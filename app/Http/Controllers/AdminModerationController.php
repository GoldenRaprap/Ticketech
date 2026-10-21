<?php

namespace App\Http\Controllers;

use App\Mail\ClientConductWarning;
use App\Models\ClientModeration;
use App\Models\TicketEvent;
use App\Models\TicketMisuseReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminModerationController extends Controller
{
    public function index(): View
    {
        return view('admin.moderation', [
            'reports' => TicketMisuseReport::query()
                ->with(['ticket', 'reporter:id,name', 'reviewedBy:id,name'])
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->latest()
                ->paginate(15, ['*'], 'reports_page'),
            'moderations' => ClientModeration::query()
                ->with(['actedBy:id,name', 'sourceReport.ticket'])
                ->latest('updated_at')
                ->paginate(15, ['*'], 'clients_page'),
            'pendingReportCount' => TicketMisuseReport::query()->where('status', 'pending')->count(),
            'activeWarningCount' => ClientModeration::query()->where('status', 'warning')->whereNull('cleared_at')->count(),
            'activeBlockCount' => ClientModeration::query()->where('status', 'blocked')->whereNull('cleared_at')->count(),
        ]);
    }

    public function review(Request $request, TicketMisuseReport $report): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:warn,block,dismiss'],
        ]);

        $email = Str::lower(trim($report->ticket->requester_email));

        DB::transaction(function () use ($request, $report, $validated, $email): void {
            $report = TicketMisuseReport::query()
                ->whereKey($report->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($report->status === 'pending', 404);

            $existingModeration = ClientModeration::query()
                ->where('email', $email)
                ->whereNull('cleared_at')
                ->lockForUpdate()
                ->first();

            if ($validated['action'] === 'warn' && $existingModeration?->status === 'blocked') {
                throw ValidationException::withMessages([
                    'action' => 'This client is already blocked. Clear the block before issuing a warning.',
                ]);
            }

            $report->update([
                'status' => 'reviewed',
                'action' => $validated['action'],
                'reviewed_by_id' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            $ticket = $report->ticket;

            if ($validated['action'] !== 'dismiss') {
                ClientModeration::updateOrCreate(
                    ['email' => $email],
                    [
                        'status' => $validated['action'] === 'block' ? 'blocked' : 'warning',
                        'reason' => $report->details ?: str_replace('_', ' ', $report->reason),
                        'source_report_id' => $report->id,
                        'acted_by_id' => $request->user()->id,
                        'cleared_at' => null,
                    ],
                );
            }

            TicketEvent::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $request->user()->id,
                'event' => 'client_moderation_reviewed',
                'description' => match ($validated['action']) {
                    'warn' => 'Administrator issued a conduct warning to the requester.',
                    'block' => 'Administrator blocked the requester email from new ticket submissions.',
                    default => 'Administrator dismissed the client conduct report.',
                },
            ]);
        });

        if ($validated['action'] === 'warn') {
            Mail::to($email)->send(new ClientConductWarning);
        }

        return to_route('admin.moderation')->with('status', 'Client report review saved.');
    }

    public function clear(ClientModeration $clientModeration): RedirectResponse
    {
        DB::transaction(function () use ($clientModeration): void {
            $clientModeration = ClientModeration::query()
                ->whereKey($clientModeration->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_if($clientModeration->cleared_at !== null, 404);

            $clientModeration->update(['cleared_at' => now()]);
        });

        return to_route('admin.moderation')->with('status', 'Client warning or block cleared.');
    }
}
