<?php

namespace App\Http\Controllers;

use App\Http\Requests\LookupTicketRequest;
use App\Http\Requests\StoreRequesterTicketReplyRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Models\Category;
use App\Models\ClientModeration;
use App\Models\Department;
use App\Models\PendingTicketSubmission;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Models\WorkspaceNotification;
use App\Notifications\TicketEmailVerification;
use App\Notifications\TicketRequesterUpdate;
use App\Services\PublicFeatureAvailability;
use App\Services\RequesterTicketDocument;
use App\Services\TicketPdf;
use App\Services\TicketPriorityClassifier;
use App\Services\TicketResolutionSla;
use App\Services\TicketSubmissionSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TicketController extends Controller
{
    public function create(TicketSubmissionSettings $submissionSettings): View
    {
        return view('helpdesk.create-ticket', [
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
            'ticketSubmissionOpen' => $submissionSettings->isOpen(),
            'ticketSubmissionMessage' => $submissionSettings->closedMessage(),
        ]);
    }

    public function store(StoreTicketRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $uploads = $request->file('attachment', []) ?? [];
        $storedAttachments = [];
        $moderation = null;
        $verificationToken = Str::random(64);
        $verificationExpiresAt = now()->addDay();

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

            $submission = DB::transaction(function () use ($request, $validated, $storedAttachments, $verificationToken, $verificationExpiresAt, &$moderation): PendingTicketSubmission {
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

                return PendingTicketSubmission::create([
                    'requester_user_id' => $request->user()?->id,
                    'requester_name' => $validated['name'],
                    'requester_email' => $validated['email'],
                    'payload' => [
                        'student_id' => $validated['student_id'],
                        'requester_phone' => $validated['phone'] ?? null,
                        'department_id' => $validated['department_id'],
                        'category_id' => $validated['category_id'] ?? null,
                        'subject' => $validated['subject'],
                        'description' => $validated['message'],
                        'moderation_warning' => $moderation?->status === 'warning',
                    ],
                    'attachments' => array_map(
                        fn (array $attachment): array => [
                            'original_name' => $attachment['upload']->getClientOriginalName(),
                            'path' => $attachment['path'],
                            'mime_type' => $attachment['upload']->getMimeType(),
                            'size_bytes' => $attachment['upload']->getSize(),
                        ],
                        $storedAttachments,
                    ),
                    'verification_token_hash' => hash('sha256', $verificationToken),
                    'expires_at' => $verificationExpiresAt,
                ]);
            });
        } catch (Throwable $exception) {
            foreach ($storedAttachments as $storedAttachment) {
                Storage::disk('private')->delete($storedAttachment['path']);
            }

            throw $exception;
        }

        $verificationUrl = URL::temporarySignedRoute(
            'ticket.email.confirm',
            $verificationExpiresAt,
            ['submission' => $submission->id, 'token' => $verificationToken],
        );

        Notification::route('mail', $submission->requester_email)->notify(new TicketEmailVerification(
            $submission->requester_name,
            $verificationUrl,
            $verificationExpiresAt->copy()
                ->timezone(config('app.display_timezone'))
                ->format('F j, Y g:i A'),
        ));

        $request->session()->flash('submitted_ticket', [
            'email' => $submission->requester_email,
            'email_verification_pending' => true,
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

    public function confirmPendingEmail(PendingTicketSubmission $submission, string $token): View
    {
        abort_unless(
            $submission->expires_at->isFuture()
                && hash_equals($submission->verification_token_hash, hash('sha256', $token)),
            403,
        );

        return view('helpdesk.ticket-email-confirm', ['submission' => $submission]);
    }

    public function completePendingSubmission(
        Request $request,
        PendingTicketSubmission $submission,
        string $token,
        TicketPriorityClassifier $priorityClassifier,
        TicketResolutionSla $resolutionSla,
        TicketPdf $ticketPdf,
    ): View|RedirectResponse {
        $moderationWarning = false;
        $ticket = DB::transaction(function () use ($submission, $token, $priorityClassifier, $resolutionSla, &$moderationWarning): ?Ticket {
            $lockedSubmission = PendingTicketSubmission::query()
                ->lockForUpdate()
                ->findOrFail($submission->id);

            abort_unless(
                $lockedSubmission->expires_at->isFuture()
                    && hash_equals($lockedSubmission->verification_token_hash, hash('sha256', $token)),
                403,
            );

            $moderation = ClientModeration::query()
                ->where('email', Str::lower(trim($lockedSubmission->requester_email)))
                ->whereNull('cleared_at')
                ->lockForUpdate()
                ->first();

            if ($moderation?->status === 'blocked') {
                $lockedSubmission->delete();

                return null;
            }

            $moderationWarning = $moderation?->status === 'warning';
            $payload = $lockedSubmission->payload;
            $requesterUserId = User::query()
                ->whereKey($lockedSubmission->requester_user_id)
                ->whereRaw('LOWER(email) = ?', [Str::lower($lockedSubmission->requester_email)])
                ->value('id');
            $ticket = Ticket::create([
                'requester_user_id' => $requesterUserId,
                'requester_name' => $lockedSubmission->requester_name,
                'student_id' => $payload['student_id'],
                'requester_email' => $lockedSubmission->requester_email,
                'requester_email_verified_at' => now(),
                'requester_phone' => $payload['requester_phone'],
                'department_id' => $payload['department_id'],
                'category_id' => $payload['category_id'],
                'subject' => $payload['subject'],
                'description' => $payload['description'],
                'priority' => $priorityClassifier->classify($payload['subject']),
                'status' => 'New',
            ]);

            $ticket->ticket_number = sprintf('ED-%s-%06d', now()->format('Y'), $ticket->getKey());
            $resolutionSla->refresh($ticket);
            $ticket->save();
            $ticket->events()->create([
                'actor_id' => $requesterUserId,
                'event' => 'ticket_created',
                'description' => 'Ticket submitted by '.$ticket->requester_name.' after email verification.',
            ]);

            foreach ($lockedSubmission->attachments as $attachment) {
                $ticket->attachments()->create([
                    'uploaded_by' => $requesterUserId,
                    'original_name' => $attachment['original_name'],
                    'disk' => 'private',
                    'path' => $attachment['path'],
                    'mime_type' => $attachment['mime_type'],
                    'size_bytes' => $attachment['size_bytes'],
                ]);
            }

            User::query()->whereIn('role', ['staff', 'admin'])->where('employment_status', 'active')->get(['id'])->each(
                fn (User $user) => WorkspaceNotification::create([
                    'user_id' => $user->id,
                    'ticket_id' => $ticket->id,
                    'actor_id' => $requesterUserId,
                    'type' => 'new_ticket',
                    'title' => 'New support ticket',
                    'message' => "{$ticket->ticket_number} · {$ticket->subject}",
                ]),
            );

            $lockedSubmission->delete();

            return $ticket;
        });

        if ($ticket === null) {
            foreach ($submission->attachments as $attachment) {
                $disk = Storage::disk('private');

                if ($disk->exists($attachment['path']) && ! $disk->delete($attachment['path'])) {
                    throw new RuntimeException('Unable to remove a blocked pending ticket attachment.');
                }
            }

            return view('helpdesk.ticket-email-rejected');
        }

        $ticket->load('department', 'category');
        Notification::route('mail', $ticket->requester_email)->notify(new TicketRequesterUpdate(
            $ticket->requester_name,
            $ticket->ticket_number,
            $ticket->subject,
            'Your email address has been verified and your support ticket has been submitted. Our team will follow up by email.',
            'Your support ticket is confirmed',
            $ticketPdf->generate($ticket),
        ));

        $request->session()->flash('submitted_ticket', [
            'ticket_number' => $ticket->ticket_number,
            'submitted_at' => $ticket->created_at->copy()
                ->timezone(config('app.display_timezone'))
                ->format('F j, Y g:i A'),
            'status' => $ticket->status,
            'email' => $ticket->requester_email,
            'email_verification_pending' => false,
            'moderation_warning' => $moderationWarning,
        ]);

        return to_route('ticket.submitted');
    }

    public function verifyEmail(Ticket $ticket, string $token, TicketPdf $ticketPdf): View
    {
        $emailWasJustVerified = DB::transaction(function () use ($ticket, $token): bool {
            $lockedTicket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            if ($lockedTicket->requester_email_verified_at !== null) {
                return false;
            }

            abort_unless(
                $lockedTicket->requester_email_verification_token_hash !== null
                    && $lockedTicket->requester_email_verification_expires_at?->isFuture()
                    && hash_equals(
                        $lockedTicket->requester_email_verification_token_hash,
                        hash('sha256', $token),
                    ),
                403,
            );

            $lockedTicket->update([
                'requester_email_verified_at' => now(),
                'requester_email_verification_token_hash' => null,
                'requester_email_verification_expires_at' => null,
            ]);

            $lockedTicket->events()->create([
                'event' => 'requester_email_verified',
                'description' => 'Requester verified the email address.',
            ]);

            return true;
        });

        if ($emailWasJustVerified) {
            $ticket->refresh()->load('department', 'category');

            Notification::route('mail', $ticket->requester_email)->notify(new TicketRequesterUpdate(
                $ticket->requester_name,
                $ticket->ticket_number,
                $ticket->subject,
                'Your email address has been verified. We received your support request and our team will follow up by email.',
                'Your support ticket is confirmed',
                $ticketPdf->generate($ticket),
            ));

            $this->notifyRequesterOfPendingStaffUpdates($ticket);
        }

        return view('helpdesk.ticket-email-verified', [
            'ticketNumber' => $ticket->ticket_number,
            'alreadyVerified' => ! $emailWasJustVerified,
        ]);
    }

    private function notifyRequesterOfPendingStaffUpdates(Ticket $ticket): void
    {
        $updates = [];

        foreach (
            $ticket->comments()
                ->where('is_internal', false)
                ->whereHas('author', fn ($query) => $query->whereIn('role', ['staff', 'admin']))
                ->oldest()
                ->get(['id', 'body', 'created_at']) as $comment
        ) {
            $updates[] = [
                'created_at' => $comment->created_at,
                'id' => $comment->id,
                'message' => $comment->body,
                'subject' => 'A reply was added to your support ticket',
            ];
        }

        foreach (
            $ticket->events()
                ->where('event', 'status_changed')
                ->whereHas('actor', fn ($query) => $query->whereIn('role', ['staff', 'admin']))
                ->oldest()
                ->get(['id', 'description', 'created_at']) as $event
        ) {
            $updates[] = [
                'created_at' => $event->created_at,
                'id' => $event->id,
                'message' => 'A status update was made: '.$event->description,
                'subject' => 'Your support ticket status changed',
            ];
        }

        usort($updates, static fn (array $first, array $second): int => $first['created_at']->getTimestamp() <=> $second['created_at']->getTimestamp()
            ?: $first['id'] <=> $second['id']);

        foreach ($updates as $update) {
            Notification::route('mail', $ticket->requester_email)->notify(new TicketRequesterUpdate(
                $ticket->requester_name,
                $ticket->ticket_number,
                $ticket->subject,
                $update['message'],
                $update['subject'],
            ));
        }
    }

    public function lookup(
        Request $request,
        PublicFeatureAvailability $availability,
        RequesterTicketDocument $ticketDocument,
    ): View {
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
            'ticketDocument' => $ticket ? $ticketDocument->forRequester($ticket) : null,
            'lookupAttempted' => $request->session()->get('ticket_lookup_failed', false),
            'rememberedEmail' => $request->session()->get('ticket_lookup_email', ''),
            'lookupTicketNumber' => $ticket ? $lookupContext['ticket'] : '',
            'lookupEmail' => $ticket ? $lookupContext['email'] : '',
            'moderationWarning' => $ticket && $this->hasActiveClientWarning($lookupContext['email']),
            'ticketRepliesEnabled' => $availability->ticketRepliesAreEnabled(),
            'ticketRepliesMessage' => $availability->ticketRepliesMessage(),
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

    public function show(
        LookupTicketRequest $request,
        PublicFeatureAvailability $availability,
        RequesterTicketDocument $ticketDocument,
    ): View {
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
            'ticketDocument' => $ticket ? $ticketDocument->forRequester($ticket) : null,
            'lookupAttempted' => true,
            'rememberedEmail' => $rememberedEmail,
            'lookupTicketNumber' => $validated['ticket'],
            'lookupEmail' => $lookupEmail,
            'moderationWarning' => $ticket && $this->hasActiveClientWarning($lookupEmail),
            'ticketRepliesEnabled' => $availability->ticketRepliesAreEnabled(),
            'ticketRepliesMessage' => $availability->ticketRepliesMessage(),
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

    public function reply(
        StoreRequesterTicketReplyRequest $request,
        TicketResolutionSla $resolutionSla,
    ): RedirectResponse {
        $validated = $request->validated();
        $ticket = $this->findRequesterTicket($validated['ticket'], $validated['email']);

        if (! $ticket) {
            $request->session()->forget('ticket_lookup_context');

            return to_route('ticket.check')->with('ticket_lookup_failed', true);
        }

        $uploads = $request->file('attachment', []) ?? [];
        $storedAttachments = [];

        try {
            $ticketWasResumed = DB::transaction(function () use (
                $ticket,
                $validated,
                $uploads,
                $resolutionSla,
                &$storedAttachments,
            ): ?bool {
                $lockedTicket = Ticket::query()->lockForUpdate()->findOrFail($ticket->getKey());

                if (in_array($lockedTicket->status, ['Resolved', 'Closed'], true)) {
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
                $ticketWasResumed = $previousStatus === 'Waiting for User';

                if ($ticketWasResumed) {
                    $lockedTicket->status = 'In Progress';

                    if ($lockedTicket->sla_paused_at !== null) {
                        $lockedTicket->sla_paused_minutes += $resolutionSla->businessMinutesBetween(
                            $lockedTicket->sla_paused_at,
                            now(),
                        );
                        $lockedTicket->sla_paused_at = null;
                    }

                    $resolutionSla->refresh($lockedTicket);
                    $lockedTicket->save();

                    $lockedTicket->events()->create([
                        'event' => 'status_changed',
                        'description' => "Ticket resumed after a requester reply ({$previousStatus} → In Progress).",
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

                Notification::route('mail', $lockedTicket->requester_email)->notify(new TicketRequesterUpdate(
                    $lockedTicket->requester_name,
                    $lockedTicket->ticket_number,
                    $lockedTicket->subject,
                    $validated['body'],
                    'A reply was added to your support ticket',
                ));

                return $ticketWasResumed;
            });
        } catch (Throwable $exception) {
            foreach ($storedAttachments as $storedAttachment) {
                Storage::disk('private')->delete($storedAttachment['path']);
            }

            throw $exception;
        }

        if ($ticketWasResumed === null) {
            $request->session()->put('ticket_lookup_context', [
                'ticket' => $ticket->ticket_number,
                'email' => $validated['email'],
            ]);

            return to_route('ticket.check')->with('ticket_reply_error', 'Resolved or closed tickets can no longer accept replies or attachments.');
        }

        $request->session()->put('ticket_lookup_context', [
            'ticket' => $ticket->ticket_number,
            'email' => $validated['email'],
        ]);
        $request->session()->forget('ticket_lookup_failed');

        $message = $ticketWasResumed
            ? 'Your reply has been sent and the ticket has resumed for staff follow-up.'
            : 'Your reply has been sent to the helpdesk.';

        return to_route('ticket.check')->with('ticket_reply_status', $message);
    }

    public function downloadTicket(
        LookupTicketRequest $request,
        RequesterTicketDocument $ticketDocument,
    ): Response {
        $validated = $request->validated();
        $ticket = $this->findRequesterTicket($validated['ticket'], $validated['email']);

        abort_unless($ticket, 404);

        $response = response()
            ->view('helpdesk.ticket-download', [
                'document' => $ticketDocument->forRequester($ticket),
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
                'assignedTo',
                'comments' => fn ($query) => $query->where('is_internal', false)->with('attachments')->oldest(),
                'attachments' => fn ($query) => $query->whereNull('comment_id'),
            ])
            ->where('ticket_number', $ticketNumber)
            ->whereRaw('LOWER(requester_email) = ?', [Str::lower($email)])
            ->first();
    }
}
