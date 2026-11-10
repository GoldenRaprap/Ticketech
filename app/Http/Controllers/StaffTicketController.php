<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStaffTicketCommentRequest;
use App\Http\Requests\TransferStaffTicketRequest;
use App\Http\Requests\UpdateStaffTicketRequest;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketEvent;
use App\Models\User;
use App\Models\WorkspaceNotification;
use App\Notifications\TicketRequesterUpdate;
use App\Services\TicketResolutionSla;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class StaffTicketController extends Controller
{
    public function index(Request $request, TicketResolutionSla $resolutionSla): View
    {
        $user = $request->user();
        $canFilterByDepartment = $user->isAdmin() || $user->isDepartmentAdmin();
        $requestedDepartmentId = $user->isDepartmentAdmin()
            ? ($request->input('department_id') ?: $user->department_id)
            : ($user->isAdmin() ? $request->input('department_id') : $user->department_id);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:New,Assigned,In Progress,Waiting for User,On Hold,Resolved,Closed'],
            'priority' => ['nullable', 'in:Urgent,High,Medium,Low'],
            'department_id' => $canFilterByDepartment
                ? ['nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)]
                : ['prohibited'],
            'assignee' => [
                'nullable',
                'string',
                'max:20',
                Rule::when(
                    $request->input('assignee') !== 'unassigned',
                    ['integer', Rule::exists('users', 'id')->where(fn ($query) => $query
                        ->where('role', 'staff')
                        ->where('employment_status', 'active')
                        ->where('staff_approval_status', 'approved')
                        ->when(
                            $requestedDepartmentId !== null,
                            fn ($query) => $query->where('department_id', $requestedDepartmentId),
                            fn ($query) => $query->whereRaw('1 = 0'),
                        ))],
                ),
            ],
        ]);

        $resolutionSla->initializeMissingDeadlines();

        $searchTerm = trim($filters['search'] ?? '');
        $selectedDepartmentId = $user->isDepartmentAdmin()
            ? (filled($filters['department_id'] ?? null)
                ? (int) $filters['department_id']
                : $user->department_id)
            : ($user->isAdmin() && filled($filters['department_id'] ?? null)
                ? (int) $filters['department_id']
                : $user->department_id);
        $departmentTickets = Ticket::query()
            ->when(
                $selectedDepartmentId !== null,
                fn ($query) => $query->where('department_id', $selectedDepartmentId),
                fn ($query) => $user->isAdmin() ? $query : $query->whereRaw('1 = 0'),
            );
        $tickets = Ticket::query()
            ->where(function ($query) use ($user, $searchTerm, $selectedDepartmentId): void {
                if (! $user->isAdmin()) {
                    if ($selectedDepartmentId === null) {
                        $query->whereRaw('1 = 0');
                    } else {
                        $query->where('department_id', $selectedDepartmentId);
                    }
                } elseif ($selectedDepartmentId !== null) {
                    $query->where('department_id', $selectedDepartmentId);
                }

                if (! $user->isAdmin() && $searchTerm !== '') {
                    $query->orWhere('ticket_number', $searchTerm);
                }
            })
            ->with(['assignedTo:id,name', 'department:id,name'])
            ->when($searchTerm !== '', function ($query) use ($searchTerm): void {
                $query->where(function ($query) use ($searchTerm): void {
                    $query->where('ticket_number', 'like', '%'.$searchTerm.'%')
                        ->orWhere('subject', 'like', '%'.$searchTerm.'%')
                        ->orWhere('requester_name', 'like', '%'.$searchTerm.'%')
                        ->orWhere('requester_email', 'like', '%'.$searchTerm.'%');
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($query, string $priority) => $query->where('priority', $priority))
            ->when(($filters['assignee'] ?? null) === 'unassigned', fn ($query) => $query->whereNull('assigned_to_user_id'))
            ->when(
                isset($filters['assignee']) && $filters['assignee'] !== '' && $filters['assignee'] !== 'unassigned',
                fn ($query) => $query->where('assigned_to_user_id', $filters['assignee']),
            )
            ->orderByRaw("CASE WHEN status IN ('New', 'Assigned', 'In Progress') THEN 0 WHEN status IN ('Waiting for User', 'On Hold') THEN 1 WHEN status IN ('Resolved', 'Closed') THEN 2 ELSE 3 END")
            ->orderByRaw(
                "CASE WHEN status IN ('New', 'Assigned', 'In Progress') THEN CASE WHEN resolution_due_at <= ? THEN 0 WHEN resolution_warning_at <= ? THEN 1 ELSE 2 END ELSE 0 END",
                [now(), now()],
            )
            ->orderByRaw("CASE priority WHEN 'Urgent' THEN 0 WHEN 'High' THEN 1 WHEN 'Medium' THEN 2 WHEN 'Low' THEN 3 ELSE 4 END")
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('staff.tickets', [
            'tickets' => $tickets,
            'filters' => $filters,
            'canFilterByDepartment' => $canFilterByDepartment,
            'departments' => $canFilterByDepartment
                ? Department::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : collect(),
            'selectedDepartmentId' => $selectedDepartmentId,
            'assignees' => User::query()
                ->where('role', 'staff')
                ->where('employment_status', 'active')
                ->where('staff_approval_status', 'approved')
                ->when(
                    $selectedDepartmentId !== null,
                    fn ($query) => $query->where('department_id', $selectedDepartmentId),
                    fn ($query) => $query->whereRaw('1 = 0'),
                )
                ->orderBy('name')
                ->get(['id', 'name', 'nickname']),
            'ticketCount' => (clone $departmentTickets)->count(),
            'newCount' => (clone $departmentTickets)->where('status', 'New')->count(),
            'inProgressCount' => (clone $departmentTickets)->where('status', 'In Progress')->count(),
            'completedCount' => (clone $departmentTickets)->whereIn('status', ['Resolved', 'Closed'])->count(),
            'departmentName' => $selectedDepartmentId !== null
                ? Department::query()->whereKey($selectedDepartmentId)->value('name')
                : null,
        ]);
    }

    public function show(Request $request, Ticket $ticket): View
    {
        $this->ensureStaffCanViewTicket($request);
        $ticket->load([
            'assignedTo:id,name,email',
            'department:id,name',
            'category:id,name',
            'comments' => fn ($query) => $query->with(['author:id,name,role', 'attachments'])->oldest(),
            'attachments' => fn ($query) => $query->whereNull('comment_id'),
            'events' => fn ($query) => $query->with('actor:id,name')->oldest(),
            'misuseReports' => fn ($query) => $query->with('reporter:id,name')->latest(),
        ]);
        $user = $request->user();
        $canWorkTicket = $ticket->isVisibleTo($user);
        $canManageTickets = $canWorkTicket && $user->canManageDepartmentTickets();
        $lastReopenedAt = $ticket->last_reopened_at ?? $ticket->events
            ->where('event', 'status_changed')
            ->filter(fn (TicketEvent $event): bool => str_starts_with($event->description, 'Ticket reopened after a requester reply'))
            ->last()?->created_at;
        $hasLatestResolution = $ticket->last_resolved_at
            && (! $lastReopenedAt || $ticket->last_resolved_at->greaterThanOrEqualTo($lastReopenedAt));

        return view('staff.ticket-detail', [
            'ticket' => $ticket,
            'canWorkTicket' => $canWorkTicket,
            'isCrossDepartmentView' => ! $canWorkTicket,
            'canManageTickets' => $canManageTickets,
            'canTransferTicket' => $canManageTickets && ! in_array($ticket->status, ['Resolved', 'Closed'], true),
            'departments' => $canManageTickets
                ? Department::query()
                    ->where('is_active', true)
                    ->when(
                        $ticket->department_id !== null,
                        fn ($query) => $query->where('id', '<>', $ticket->department_id),
                    )
                    ->orderBy('name')
                    ->get(['id', 'name'])
                : collect(),
            'staffUsers' => $canManageTickets
                ? User::query()
                    ->where('employment_status', 'active')
                    ->where('staff_approval_status', 'approved')
                    ->where(function ($query) use ($ticket, $user): void {
                        $query->where(function ($query) use ($ticket): void {
                            if ($ticket->department_id === null) {
                                $query->whereRaw('1 = 0');
                            } else {
                                $query->where('role', 'staff')
                                    ->where('department_id', $ticket->department_id);
                            }
                        });

                        if ($user->isAdmin()) {
                            $query->orWhere('role', 'admin');
                        }
                    })
                    ->orderBy('name')
                    ->get(['id', 'name', 'nickname'])
                : collect(),
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

    public function updates(Request $request, Ticket $ticket): JsonResponse
    {
        $this->ensureStaffCanViewTicket($request);

        return response()->json([
            'latest_comment_id' => $ticket->comments()->max('id') ?? 0,
        ]);
    }

    public function update(
        UpdateStaffTicketRequest $request,
        Ticket $ticket,
        TicketResolutionSla $resolutionSla,
    ): RedirectResponse {
        $validated = $request->validated();
        $originalAssigneeId = $ticket->assigned_to_user_id;
        $previousStatus = $ticket->status;
        $previousPriority = $ticket->priority;
        $wasSlaPaused = in_array($previousStatus, ['Waiting for User', 'On Hold'], true);

        DB::transaction(function () use (
            $request,
            $ticket,
            $validated,
            $originalAssigneeId,
            $previousStatus,
            $previousPriority,
            $wasSlaPaused,
            $resolutionSla,
        ): void {
            $assigneeId = array_key_exists('assigned_to_user_id', $validated)
                ? ($validated['assigned_to_user_id'] !== null ? (int) $validated['assigned_to_user_id'] : null)
                : $originalAssigneeId;
            $ticket->assigned_to_user_id = $assigneeId;
            $ticket->priority = $validated['priority'] ?? $ticket->priority;
            $ticket->status = $validated['status'];
            $isSlaPaused = in_array($ticket->status, ['Waiting for User', 'On Hold'], true);

            if ($assigneeId !== null && $originalAssigneeId !== $assigneeId) {
                $ticket->assigned_at = now();
                $ticket->first_assigned_at ??= $ticket->assigned_at;
            }

            if (! $wasSlaPaused && $isSlaPaused) {
                $ticket->sla_paused_at = now();
            } elseif ($wasSlaPaused && ! $isSlaPaused && $ticket->sla_paused_at !== null) {
                $ticket->sla_paused_minutes += $resolutionSla->businessMinutesBetween($ticket->sla_paused_at, now());
                $ticket->sla_paused_at = null;
            }

            $isReopening = in_array($previousStatus, ['Resolved', 'Closed'], true)
                && ! in_array($ticket->status, ['Resolved', 'Closed'], true);

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

            if ($isReopening) {
                $ticket->sla_paused_minutes = 0;
                $ticket->sla_paused_at = $isSlaPaused ? now() : null;
            }

            if (
                $isReopening
                || $previousPriority !== $ticket->priority
                || ($wasSlaPaused && ! $isSlaPaused)
                || $ticket->resolution_due_at === null
            ) {
                $resolutionSla->refresh($ticket);
                $ticket->save();
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

        if ($previousStatus !== $ticket->status && $ticket->canEmailRequester()) {
            Notification::route('mail', $ticket->requester_email)->notify(new TicketRequesterUpdate(
                $ticket->requester_name,
                $ticket->ticket_number,
                $ticket->subject,
                "The status of your support ticket changed from {$previousStatus} to {$ticket->status}.",
                'Your support ticket status changed',
            ));
        }

        return to_route('staff.ticket-detail', $ticket)->with('status', 'Ticket updated successfully.');
    }

    public function transfer(TransferStaffTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $validated = $request->validated();
        $actor = $request->user();

        DB::transaction(function () use ($ticket, $validated, $actor): void {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            abort_unless($ticket->isVisibleTo($actor), 404);
            abort_if(in_array($ticket->status, ['Resolved', 'Closed'], true), 403);

            $sourceDepartment = $ticket->department;
            $targetDepartment = Department::query()
                ->where('is_active', true)
                ->lockForUpdate()
                ->findOrFail($validated['department_id']);
            abort_if($ticket->department_id === $targetDepartment->id, 403);

            $previousDepartmentId = $ticket->department_id;
            $previousAssigneeId = $ticket->assigned_to_user_id;
            $ticket->department_id = $targetDepartment->id;
            $ticket->assigned_to_user_id = null;
            $ticket->assigned_at = null;
            $ticket->save();

            $ticket->comments()->create([
                'author_id' => $actor->id,
                'author_name' => $actor->name,
                'body' => "Handoff to {$targetDepartment->name}:\n\n{$validated['handoff_note']}",
                'is_internal' => true,
            ]);

            TicketEvent::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $actor->id,
                'event' => 'department_transferred',
                'description' => sprintf(
                    'Ticket transferred from %s to %s.',
                    $sourceDepartment?->name ?? 'an unassigned department',
                    $targetDepartment->name,
                ),
                'properties' => [
                    'from_department_id' => $previousDepartmentId,
                    'to_department_id' => $targetDepartment->id,
                    'previous_assignee_id' => $previousAssigneeId,
                ],
            ]);

            if ($previousAssigneeId !== null) {
                TicketEvent::create([
                    'ticket_id' => $ticket->id,
                    'actor_id' => $actor->id,
                    'event' => 'assignment_changed',
                    'description' => 'Ticket assignment removed during department transfer.',
                ]);
            }

            User::query()
                ->where('role', 'department_admin')
                ->where('department_id', $targetDepartment->id)
                ->where('employment_status', 'active')
                ->where('staff_approval_status', 'approved')
                ->get(['id'])
                ->each(fn (User $departmentAdmin) => WorkspaceNotification::create([
                    'user_id' => $departmentAdmin->id,
                    'ticket_id' => $ticket->id,
                    'actor_id' => $actor->id,
                    'type' => 'department_transfer',
                    'title' => 'Ticket transferred to your department',
                    'message' => "{$ticket->ticket_number} · {$ticket->subject}",
                ]));
        });

        return to_route('staff.ticket-detail', $ticket)
            ->with('status', 'Ticket transferred to the receiving department queue.');
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

                if (! $isInternal && $ticket->canEmailRequester()) {
                    Notification::route('mail', $ticket->requester_email)->notify(new TicketRequesterUpdate(
                        $ticket->requester_name,
                        $ticket->ticket_number,
                        $ticket->subject,
                        $validated['body'],
                        'A reply was added to your support ticket',
                    ));
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
        $this->ensureTicketVisible($request, $ticket);

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

    public function downloadAttachment(Request $request, Ticket $ticket, TicketAttachment $attachment): StreamedResponse
    {
        $this->ensureStaffCanViewTicket($request);
        abort_unless($attachment->ticket_id === $ticket->id && $attachment->disk === 'private', 404);

        $disk = Storage::disk('private');
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->download($attachment->path, $attachment->original_name);
    }

    private function ensureTicketVisible(Request $request, Ticket $ticket): void
    {
        abort_unless($ticket->isVisibleTo($request->user()), 404);
    }

    private function ensureStaffCanViewTicket(Request $request): void
    {
        abort_unless($request->user()?->isStaff(), 404);
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
