<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStaffTicketCommentRequest;
use App\Http\Requests\UpdateStaffTicketRequest;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketEvent;
use App\Models\User;
use App\Models\WorkspaceNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class StaffTicketController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:New,Assigned,In Progress,Waiting for User,On Hold,Resolved,Closed'],
            'priority' => ['nullable', 'in:Urgent,High,Medium,Low'],
            'assignee' => [
                'nullable',
                'string',
                'max:20',
                Rule::when(
                    $request->input('assignee') !== 'unassigned',
                    ['integer', Rule::exists('users', 'id')->where(fn ($query) => $query
                        ->whereIn('role', ['staff', 'admin'])
                        ->where('employment_status', 'active'))],
                ),
            ],
        ]);

        $tickets = Ticket::query()
            ->with('assignedTo:id,name')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('ticket_number', 'like', '%'.$search.'%')
                        ->orWhere('subject', 'like', '%'.$search.'%')
                        ->orWhere('requester_name', 'like', '%'.$search.'%')
                        ->orWhere('requester_email', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($query, string $priority) => $query->where('priority', $priority))
            ->when(($filters['assignee'] ?? null) === 'unassigned', fn ($query) => $query->whereNull('assigned_to_user_id'))
            ->when(
                isset($filters['assignee']) && $filters['assignee'] !== '' && $filters['assignee'] !== 'unassigned',
                fn ($query) => $query->where('assigned_to_user_id', $filters['assignee']),
            )
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('staff.tickets', [
            'tickets' => $tickets,
            'filters' => $filters,
            'assignees' => User::query()->whereIn('role', ['staff', 'admin'])->where('employment_status', 'active')->orderBy('name')->get(['id', 'name']),
            'ticketCount' => Ticket::query()->count(),
            'newCount' => Ticket::query()->where('status', 'New')->count(),
            'inProgressCount' => Ticket::query()->where('status', 'In Progress')->count(),
            'completedCount' => Ticket::query()->whereIn('status', ['Resolved', 'Closed'])->count(),
        ]);
    }

    public function show(Ticket $ticket): View
    {
        $ticket->load([
            'assignedTo:id,name,email',
            'comments' => fn ($query) => $query->with(['author:id,name,role', 'attachments'])->oldest(),
            'attachments' => fn ($query) => $query->whereNull('comment_id'),
            'events' => fn ($query) => $query->with('actor:id,name')->oldest(),
            'misuseReports' => fn ($query) => $query->with('reporter:id,name')->latest(),
        ]);
        $lastReopenedAt = $ticket->last_reopened_at ?? $ticket->events
            ->where('event', 'status_changed')
            ->filter(fn (TicketEvent $event): bool => str_starts_with($event->description, 'Ticket reopened after a requester reply'))
            ->last()?->created_at;
        $hasLatestResolution = $ticket->last_resolved_at
            && (! $lastReopenedAt || $ticket->last_resolved_at->greaterThanOrEqualTo($lastReopenedAt));

        return view('staff.ticket-detail', [
            'ticket' => $ticket,
            'staffUsers' => User::query()->whereIn('role', ['staff', 'admin'])->where('employment_status', 'active')->orderBy('name')->get(['id', 'name']),
            'ticketTiming' => [
                'first_assignment' => $ticket->first_assigned_at
                    ? $this->formatDuration($ticket->created_at->diffInSeconds($ticket->first_assigned_at))
                    : null,
                'latest_resolution' => $hasLatestResolution
                    ? $this->formatDuration(($lastReopenedAt ?? $ticket->created_at)->diffInSeconds($ticket->last_resolved_at))
                    : null,
                'total_elapsed' => $ticket->last_resolved_at
                    ? $this->formatDuration($ticket->created_at->diffInSeconds($ticket->last_resolved_at))
                    : null,
            ],
        ]);
    }

    public function updates(Ticket $ticket): JsonResponse
    {
        return response()->json([
            'latest_comment_id' => $ticket->comments()->max('id') ?? 0,
        ]);
    }

    public function update(UpdateStaffTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $validated = $request->validated();
        $originalAssigneeId = $ticket->assigned_to_user_id;
        $previousStatus = $ticket->status;
        $previousPriority = $ticket->priority;

        DB::transaction(function () use ($request, $ticket, $validated, $originalAssigneeId, $previousStatus, $previousPriority): void {
            $assigneeId = array_key_exists('assigned_to_user_id', $validated)
                ? ($validated['assigned_to_user_id'] !== null ? (int) $validated['assigned_to_user_id'] : null)
                : $originalAssigneeId;
            $ticket->assigned_to_user_id = $assigneeId;
            $ticket->priority = $validated['priority'];
            $ticket->status = $validated['status'];

            if ($assigneeId !== null && $originalAssigneeId !== $assigneeId) {
                $ticket->assigned_at = now();
                $ticket->first_assigned_at ??= $ticket->assigned_at;
            }

            if (in_array($ticket->status, ['Resolved', 'Closed'], true)) {
                if (! in_array($previousStatus, ['Resolved', 'Closed'], true) || $ticket->resolved_at === null) {
                    $ticket->resolved_at = now();
                    $ticket->last_resolved_at = $ticket->resolved_at;
                }
            } else {
                if (in_array($previousStatus, ['Resolved', 'Closed'], true)) {
                    $ticket->last_reopened_at = now();
                }

                $ticket->resolved_at = null;
            }

            $ticket->save();

            if ($previousStatus !== $ticket->status) {
                TicketEvent::create([
                    'ticket_id' => $ticket->id,
                    'actor_id' => $request->user()->id,
                    'event' => 'status_changed',
                    'description' => "Status changed from {$previousStatus} to {$ticket->status}.",
                ]);
            }

            if ($previousPriority !== $ticket->priority) {
                TicketEvent::create([
                    'ticket_id' => $ticket->id,
                    'actor_id' => $request->user()->id,
                    'event' => 'priority_changed',
                    'description' => "Priority changed to {$ticket->priority}.",
                ]);
            }

            if ($originalAssigneeId !== $assigneeId) {
                $assignee = $assigneeId ? User::find($assigneeId) : null;
                TicketEvent::create([
                    'ticket_id' => $ticket->id,
                    'actor_id' => $request->user()->id,
                    'event' => 'assignment_changed',
                    'description' => $assignee ? "Assigned to {$assignee->name}." : 'Ticket assignment removed.',
                ]);

                if ($assignee && $assignee->id !== $request->user()->id) {
                    WorkspaceNotification::create([
                        'user_id' => $assignee->id,
                        'ticket_id' => $ticket->id,
                        'actor_id' => $request->user()->id,
                        'type' => 'assignment',
                        'title' => 'Ticket assigned to you',
                        'message' => "{$ticket->ticket_number} · {$ticket->subject}",
                    ]);
                }
            }
        });

        return to_route('staff.ticket-detail', $ticket)->with('status', 'Ticket updated successfully.');
    }

    public function comment(StoreStaffTicketCommentRequest $request, Ticket $ticket): RedirectResponse
    {
        $validated = $request->validated();
        $isInternal = (bool) ($validated['is_internal'] ?? false);
        $uploads = $request->file('attachment', []) ?? [];
        $storedAttachments = [];

        try {
            foreach ($uploads as $upload) {
                $path = $upload->store('ticket-attachments', 'private');

                if (! is_string($path) || $path === '') {
                    throw new RuntimeException('Unable to store a staff ticket comment attachment.');
                }

                $storedAttachments[] = [
                    'upload' => $upload,
                    'path' => $path,
                ];
            }

            DB::transaction(function () use ($request, $ticket, $validated, $isInternal, $storedAttachments): void {
                $comment = $ticket->comments()->create([
                    'author_id' => $request->user()->id,
                    'author_name' => $request->user()->name,
                    'body' => $validated['body'],
                    'is_internal' => $isInternal,
                ]);

                foreach ($storedAttachments as $storedAttachment) {
                    $upload = $storedAttachment['upload'];
                    $comment->attachments()->create([
                        'ticket_id' => $ticket->id,
                        'uploaded_by' => $request->user()->id,
                        'original_name' => $upload->getClientOriginalName(),
                        'disk' => 'private',
                        'path' => $storedAttachment['path'],
                        'mime_type' => $upload->getMimeType(),
                        'size_bytes' => $upload->getSize(),
                    ]);
                }

                TicketEvent::create([
                    'ticket_id' => $ticket->id,
                    'actor_id' => $request->user()->id,
                    'event' => $isInternal ? 'internal_note_added' : 'comment_added',
                    'description' => $isInternal
                        ? "{$request->user()->name} added an internal note."
                        : "{$request->user()->name} replied to the requester.",
                ]);

                if (! $isInternal && $ticket->assigned_to_user_id && $ticket->assigned_to_user_id !== $request->user()->id) {
                    WorkspaceNotification::create([
                        'user_id' => $ticket->assigned_to_user_id,
                        'ticket_id' => $ticket->id,
                        'actor_id' => $request->user()->id,
                        'type' => 'reply',
                        'title' => 'New ticket reply',
                        'message' => "{$ticket->ticket_number} · {$ticket->subject}",
                    ]);
                }
            });
        } catch (Throwable $exception) {
            foreach ($storedAttachments as $storedAttachment) {
                Storage::disk('private')->delete($storedAttachment['path']);
            }

            throw $exception;
        }

        return to_route('staff.ticket-detail', $ticket)->with('status', 'Reply added successfully.');
    }

    public function reportClient(Request $request, Ticket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'in:spam,abusive,irrelevant,repeated,other'],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $ticket, $validated): void {
            $report = $ticket->misuseReports()->create([
                'reporter_id' => $request->user()->id,
                'reason' => $validated['reason'],
                'details' => $validated['details'] ?? null,
            ]);

            TicketEvent::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $request->user()->id,
                'event' => 'client_reported',
                'description' => "{$request->user()->name} submitted a client conduct report for administrator review.",
            ]);

            User::query()->where('role', 'admin')->get(['id'])->each(
                fn (User $admin) => WorkspaceNotification::create([
                    'user_id' => $admin->id,
                    'ticket_id' => $ticket->id,
                    'actor_id' => $request->user()->id,
                    'type' => 'client_report',
                    'title' => 'Client conduct report for review',
                    'message' => "{$ticket->ticket_number} · {$validated['reason']}",
                ]),
            );
        });

        return to_route('staff.ticket-detail', $ticket)->with('status', 'Client report submitted for administrator review.');
    }

    public function downloadAttachment(Ticket $ticket, TicketAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->ticket_id === $ticket->id && $attachment->disk === 'private', 404);

        $disk = Storage::disk('private');
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->download($attachment->path, $attachment->original_name);
    }

    private function formatDuration(float|int $seconds): string
    {
        $minutes = intdiv(max(0, (int) $seconds), 60);
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $remainingMinutes = $minutes % 60;

        if ($days > 0) {
            return "{$days}d {$hours}h {$remainingMinutes}m";
        }

        if ($hours > 0) {
            return "{$hours}h {$remainingMinutes}m";
        }

        return $minutes > 0 ? "{$minutes}m" : 'Under 1m';
    }
}
