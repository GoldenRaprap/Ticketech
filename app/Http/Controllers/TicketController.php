<?php

namespace App\Http\Controllers;

use App\Http\Requests\LookupTicketRequest;
use App\Http\Requests\StoreRequesterTicketReplyRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Models\ClientModeration;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Models\WorkspaceNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TicketController extends Controller
{
    public function create(): View
    {
        return view('helpdesk.create-ticket');
    }

    public function store(StoreTicketRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $uploads = $request->file('attachment', []) ?? [];
        $storedAttachments = [];
        $moderation = null;

        try {
            foreach ($uploads as $upload) {
                $path = $upload->store('ticket-attachments', 'private');

                if (! is_string($path) || $path === '') {
                    throw new RuntimeException('Unable to store a ticket attachment.');
                }

                $storedAttachments[] = [
                    'upload' => $upload,
                    'path' => $path,
                ];
            }

            $ticket = DB::transaction(function () use ($request, $validated, $storedAttachments, &$moderation): Ticket {
                $moderation = ClientModeration::query()
                    ->where('email', Str::lower(trim($validated['email'])))
                    ->whereNull('cleared_at')
                    ->lockForUpdate()
                    ->first();

                if ($moderation?->status === 'blocked') {
                    throw ValidationException::withMessages([
                        'email' => 'Ticket submissions from this email address are blocked. Contact PCU Helpdesk administration if you believe this is a mistake.',
                    ]);
                }

                $ticket = Ticket::create([
                    'requester_user_id' => $request->user()?->id,
                    'requester_name' => $validated['name'],
                    'student_id' => $validated['student_id'],
                    'requester_email' => $validated['email'],
                    'requester_phone' => $validated['phone'] ?? null,
                    'subject' => $validated['subject'],
                    'description' => $validated['message'],
                    'priority' => $validated['priority'] ?? 'Low',
                    'status' => 'New',
                ]);

                $ticket->ticket_number = sprintf('ED-%s-%06d', now()->format('Y'), $ticket->getKey());
                $ticket->save();
                $ticket->events()->create([
                    'actor_id' => $request->user()?->id,
                    'event' => 'ticket_created',
                    'description' => 'Ticket submitted by '.$ticket->requester_name.'.',
                ]);

                User::query()->whereIn('role', ['staff', 'admin'])->where('employment_status', 'active')->get(['id'])->each(
                    fn (User $user) => WorkspaceNotification::create([
                        'user_id' => $user->id,
                        'ticket_id' => $ticket->id,
                        'actor_id' => $request->user()?->id,
                        'type' => 'new_ticket',
                        'title' => 'New support ticket',
                        'message' => "{$ticket->ticket_number} · {$ticket->subject}",
                    ]),
                );

                foreach ($storedAttachments as $storedAttachment) {
                    $upload = $storedAttachment['upload'];
                    $ticket->attachments()->create([
                        'uploaded_by' => $request->user()?->id,
                        'original_name' => $upload->getClientOriginalName(),
                        'disk' => 'private',
                        'path' => $storedAttachment['path'],
                        'mime_type' => $upload->getMimeType(),
                        'size_bytes' => $upload->getSize(),
                    ]);
                }

                return $ticket;
            });
        } catch (Throwable $exception) {
            foreach ($storedAttachments as $storedAttachment) {
                Storage::disk('private')->delete($storedAttachment['path']);
            }

            throw $exception;
        }

        $request->session()->flash('submitted_ticket', [
            'ticket_number' => $ticket->ticket_number,
            'submitted_at' => $ticket->created_at->copy()
                ->timezone(config('app.display_timezone'))
                ->format('F j, Y g:i A'),
            'status' => $ticket->status,
            'email' => $ticket->requester_email,
            'moderation_warning' => $moderation?->status === 'warning',
        ]);

        return to_route('ticket.submitted');
    }

    public function submitted(Request $request): View|RedirectResponse
    {
        $submittedTicket = $request->session()->get('submitted_ticket');

        if (! is_array($submittedTicket)) {
            return to_route('ticket.create');
        }

        return view('helpdesk.ticket-submitted', ['submittedTicket' => $submittedTicket]);
    }

    public function lookup(Request $request): View
    {
        $lookupContext = $request->session()->get('ticket_lookup_context');
        $ticket = null;

        if (
            is_array($lookupContext)
            && is_string($lookupContext['ticket'] ?? null)
            && is_string($lookupContext['email'] ?? null)
        ) {
            $ticket = $this->findRequesterTicket($lookupContext['ticket'], $lookupContext['email']);

            if (! $ticket) {
                $request->session()->forget('ticket_lookup_context');
            }
        }

        return view('helpdesk.check-ticket', [
            'ticket' => $ticket,
            'lookupAttempted' => $request->session()->get('ticket_lookup_failed', false),
            'rememberedEmail' => $request->session()->get('ticket_lookup_email', ''),
            'lookupTicketNumber' => $ticket ? $lookupContext['ticket'] : '',
            'lookupEmail' => $ticket ? $lookupContext['email'] : '',
            'moderationWarning' => $ticket && $this->hasActiveClientWarning($lookupContext['email']),
        ]);
    }

    public function closeView(Request $request): RedirectResponse
    {
        $request->session()->forget([
            'ticket_lookup_context',
            'ticket_lookup_failed',
            'ticket_reply_status',
        ]);

        return to_route('ticket.check')
            ->with('ticket_view_closed', 'Ticket view closed. Your ticket details are hidden from this browser.');
    }

    public function show(LookupTicketRequest $request): View
    {
        $validated = $request->validated();
        $lookupEmail = $validated['email'];
        $rememberedEmail = $request->boolean('remember_email') ? $lookupEmail : '';

        if ($rememberedEmail !== '') {
            $request->session()->put('ticket_lookup_email', $rememberedEmail);
        } else {
            $request->session()->forget('ticket_lookup_email');
        }

        $ticket = $this->findRequesterTicket($validated['ticket'], $lookupEmail);

        if ($ticket) {
            $request->session()->put('ticket_lookup_context', [
                'ticket' => $ticket->ticket_number,
                'email' => $lookupEmail,
            ]);
            $request->session()->forget('ticket_lookup_failed');
        } else {
            $request->session()->forget('ticket_lookup_context');
        }

        return view('helpdesk.check-ticket', [
            'ticket' => $ticket,
            'lookupAttempted' => true,
            'rememberedEmail' => $rememberedEmail,
            'lookupTicketNumber' => $validated['ticket'],
            'lookupEmail' => $lookupEmail,
            'moderationWarning' => $ticket && $this->hasActiveClientWarning($lookupEmail),
        ]);
    }

    private function hasActiveClientWarning(string $email): bool
    {
        return ClientModeration::query()
            ->where('email', Str::lower(trim($email)))
            ->where('status', 'warning')
            ->whereNull('cleared_at')
            ->exists();
    }

    public function reply(StoreRequesterTicketReplyRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $ticket = $this->findRequesterTicket($validated['ticket'], $validated['email']);

        if (! $ticket) {
            $request->session()->forget('ticket_lookup_context');

            return to_route('ticket.check')->with('ticket_lookup_failed', true);
        }

        $uploads = $request->file('attachment', []) ?? [];
        $storedAttachments = [];

        try {
            $ticketWasReopened = DB::transaction(function () use ($ticket, $validated, $uploads, &$storedAttachments): ?bool {
                $lockedTicket = Ticket::query()->lockForUpdate()->findOrFail($ticket->getKey());

                if ($lockedTicket->status === 'Closed') {
                    return null;
                }

                foreach ($uploads as $upload) {
                    $path = $upload->store('ticket-attachments', 'private');

                    if (! is_string($path) || $path === '') {
                        throw new RuntimeException('Unable to store a requester reply attachment.');
                    }

                    $storedAttachments[] = [
                        'upload' => $upload,
                        'path' => $path,
                    ];
                }

                $previousStatus = $lockedTicket->status;
                $ticketWasReopened = $previousStatus === 'Resolved';

                if ($ticketWasReopened) {
                    $lockedTicket->status = 'In Progress';
                    $lockedTicket->resolved_at = null;
                    $lockedTicket->last_reopened_at = now();
                    $lockedTicket->save();

                    $lockedTicket->events()->create([
                        'event' => 'status_changed',
                        'description' => "Ticket reopened after a requester reply ({$previousStatus} → In Progress).",
                    ]);
                }

                $comment = $lockedTicket->comments()->create([
                    'author_name' => $lockedTicket->requester_name,
                    'body' => $validated['body'],
                    'is_internal' => false,
                ]);

                foreach ($storedAttachments as $storedAttachment) {
                    $upload = $storedAttachment['upload'];
                    $comment->attachments()->create([
                        'ticket_id' => $lockedTicket->id,
                        'original_name' => $upload->getClientOriginalName(),
                        'disk' => 'private',
                        'path' => $storedAttachment['path'],
                        'mime_type' => $upload->getMimeType(),
                        'size_bytes' => $upload->getSize(),
                    ]);
                }

                $lockedTicket->events()->create([
                    'event' => 'requester_reply',
                    'description' => "{$lockedTicket->requester_name} replied to the ticket.",
                ]);

                $recipients = $lockedTicket->assigned_to_user_id
                    ? User::query()->whereKey($lockedTicket->assigned_to_user_id)->where('employment_status', 'active')
                    : User::query()->whereIn('role', ['staff', 'admin'])->where('employment_status', 'active');

                $recipients->get(['id'])->each(
                    fn (User $user) => WorkspaceNotification::create([
                        'user_id' => $user->id,
                        'ticket_id' => $lockedTicket->id,
                        'type' => 'requester_reply',
                        'title' => 'New requester reply',
                        'message' => "{$lockedTicket->ticket_number} · {$lockedTicket->subject}",
                    ]),
                );

                return $ticketWasReopened;
            });
        } catch (Throwable $exception) {
            foreach ($storedAttachments as $storedAttachment) {
                Storage::disk('private')->delete($storedAttachment['path']);
            }

            throw $exception;
        }

        if ($ticketWasReopened === null) {
            $request->session()->put('ticket_lookup_context', [
                'ticket' => $ticket->ticket_number,
                'email' => $validated['email'],
            ]);

            return to_route('ticket.check')->with('ticket_reply_error', 'This ticket is closed and can no longer accept replies or attachments.');
        }

        $request->session()->put('ticket_lookup_context', [
            'ticket' => $ticket->ticket_number,
            'email' => $validated['email'],
        ]);
        $request->session()->forget('ticket_lookup_failed');

        $message = $ticketWasReopened
            ? 'Your reply has been sent and the ticket has been reopened for staff follow-up.'
            : 'Your reply has been sent to the helpdesk.';

        return to_route('ticket.check')->with('ticket_reply_status', $message);
    }

    public function downloadTicket(LookupTicketRequest $request): Response
    {
        $validated = $request->validated();
        $ticket = $this->findRequesterTicket($validated['ticket'], $validated['email']);

        abort_unless($ticket, 404);

        $response = response()
            ->view('helpdesk.ticket-download', [
                'ticket' => $ticket,
                'autoPrint' => $request->boolean('print'),
            ])
            ->header('Content-Type', 'text/html; charset=UTF-8');

        if (! $request->boolean('print')) {
            $response->header('Content-Disposition', 'attachment; filename="ticket-'.Str::slug($ticket->ticket_number).'.html"');
        }

        return $response;
    }

    public function download(LookupTicketRequest $request, TicketAttachment $attachment): StreamedResponse
    {
        $validated = $request->validated();
        $belongsToVerifiedTicket = $attachment->ticket()
            ->where('ticket_number', $validated['ticket'])
            ->whereRaw('LOWER(requester_email) = ?', [Str::lower($validated['email'])])
            ->exists();

        abort_unless($belongsToVerifiedTicket && $attachment->disk === 'private', 404);

        if ($attachment->comment_id !== null) {
            abort_unless(
                $attachment->comment()
                    ->where('ticket_id', $attachment->ticket_id)
                    ->where('is_internal', false)
                    ->exists(),
                404,
            );
        }

        $disk = Storage::disk('private');
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->download($attachment->path, $attachment->original_name);
    }

    private function findRequesterTicket(string $ticketNumber, string $email): ?Ticket
    {
        return Ticket::query()
            ->with([
                'comments' => fn ($query) => $query->where('is_internal', false)->with('attachments')->oldest(),
                'attachments' => fn ($query) => $query->whereNull('comment_id'),
            ])
            ->where('ticket_number', $ticketNumber)
            ->whereRaw('LOWER(requester_email) = ?', [Str::lower($email)])
            ->first();
    }
}
