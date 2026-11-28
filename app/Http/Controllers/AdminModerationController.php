<?php

namespace App\Http\Controllers;

use App\Mail\ClientConductWarning;
use App\Models\ClientConductPhrase;
use App\Models\ClientModeration;
use App\Models\TicketEvent;
use App\Models\TicketMisuseReport;
use App\Services\AuditActivityLogger;
use App\Services\ClientConductScreening;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminModerationController extends Controller
{
    public function index(): View
    {
        return view('admin.moderation', [
            'conductPhrases' => ClientConductPhrase::query()
                ->orderBy('reason')
                ->orderBy('phrase')
                ->get(),
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

    public function storePhrase(
        Request $request,
        ClientConductScreening $conductScreening,
        AuditActivityLogger $auditLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'phrase' => ['required', 'string', 'max:150'],
            'reason' => ['required', 'in:threatening,abusive'],
        ]);
        $request->merge(['phrase' => $conductScreening->normalizeText($validated['phrase'])]);
        $validated = $request->validate([
            'phrase' => ['required', 'string', 'max:150', Rule::unique('client_conduct_phrases', 'phrase')],
            'reason' => ['required', 'in:threatening,abusive'],
        ]);
        $phrase = ClientConductPhrase::query()->create([
            'phrase' => $validated['phrase'],
            'reason' => $validated['reason'],
            'is_active' => true,
        ]);

        $auditLogger->record(
            $request,
            'client_conduct_phrase_added',
            'Added an automatic client conduct screening phrase.',
            $phrase,
            ['reason' => $phrase->reason],
        );

        return to_route('admin.moderation')->with('status', 'Screening phrase added.');
    }

    public function updatePhrase(
        Request $request,
        ClientConductPhrase $phrase,
        ClientConductScreening $conductScreening,
        AuditActivityLogger $auditLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'phrase' => ['required', 'string', 'max:150'],
            'reason' => ['required', 'in:threatening,abusive'],
        ]);
        $request->merge(['phrase' => $conductScreening->normalizeText($validated['phrase'])]);
        $validated = $request->validate([
            'phrase' => [
                'required',
                'string',
                'max:150',
                Rule::unique('client_conduct_phrases', 'phrase')->ignore($phrase->id),
            ],
            'reason' => ['required', 'in:threatening,abusive'],
        ]);
        $phrase->update($validated);

        $auditLogger->record(
            $request,
            'client_conduct_phrase_updated',
            'Updated an automatic client conduct screening phrase.',
            $phrase,
            ['reason' => $phrase->reason],
        );

        return to_route('admin.moderation')->with('status', 'Screening phrase updated.');
    }

    public function togglePhrase(
        Request $request,
        ClientConductPhrase $phrase,
        AuditActivityLogger $auditLogger,
    ): RedirectResponse {
        $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);
        $phrase->update(['is_active' => $request->boolean('is_active')]);

        $auditLogger->record(
            $request,
            'client_conduct_phrase_activation_updated',
            $phrase->is_active
                ? 'Enabled an automatic client conduct screening phrase.'
                : 'Disabled an automatic client conduct screening phrase.',
            $phrase,
            ['is_active' => $phrase->is_active],
        );

        return to_route('admin.moderation')->with('status', 'Screening phrase status updated.');
    }

    public function review(
        Request $request,
        TicketMisuseReport $report,
        AuditActivityLogger $auditLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'action' => ['required', 'in:warn,block,dismiss'],
        ]);

        $email = Str::lower(trim($report->ticket->requester_email));

        DB::transaction(function () use ($request, $report, $validated, $email, $auditLogger): void {
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
            $auditLogger->record(
                $request,
                'client_moderation_reviewed',
                'Reviewed a client conduct report.',
                $report,
                ['action' => $validated['action']],
            );
        });

        if ($validated['action'] === 'warn') {
            Mail::to($email)->send(new ClientConductWarning);
        }

        return to_route('admin.moderation')->with('status', 'Client report review saved.');
    }

    public function clear(
        Request $request,
        ClientModeration $clientModeration,
        AuditActivityLogger $auditLogger,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $clientModeration, $auditLogger): void {
            $clientModeration = ClientModeration::query()
                ->whereKey($clientModeration->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_if($clientModeration->cleared_at !== null, 404);

            $clientModeration->update(['cleared_at' => now()]);
            $auditLogger->record(
                $request,
                'client_moderation_cleared',
                'Cleared a client warning or block.',
                $clientModeration,
                ['status' => $clientModeration->status],
            );
        });

        return to_route('admin.moderation')->with('status', 'Client warning or block cleared.');
    }
}
