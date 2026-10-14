<?php

namespace App\Http\Controllers;

use App\Http\Requests\LookupTicketRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Models\Category;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TicketController extends Controller
{
    public function create(): View
    {
        return view('pages.create-ticket', [
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreTicketRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $upload = $request->file('attachment');
        $attachmentPath = $upload?->store('ticket-attachments', 'private');

        try {
            $ticket = DB::transaction(function () use ($request, $validated, $upload, $attachmentPath): Ticket {
                $ticket = Ticket::create([
                    'requester_user_id' => $request->user()?->id,
                    'department_id' => $validated['department_id'],
                    'category_id' => $validated['category_id'] ?? null,
                    'requester_name' => $validated['name'],
                    'student_id' => $validated['student_id'],
                    'requester_email' => $validated['email'],
                    'requester_phone' => $validated['phone'] ?? null,
                    'subject' => $validated['subject'],
                    'description' => $validated['message'],
                    'priority' => $validated['priority'],
                    'status' => 'New',
                ]);

                $ticket->ticket_number = sprintf('ED-%s-%06d', now()->format('Y'), $ticket->getKey());
                $ticket->save();

                if ($upload && $attachmentPath) {
                    $ticket->attachments()->create([
                        'uploaded_by' => $request->user()?->id,
                        'original_name' => $upload->getClientOriginalName(),
                        'disk' => 'private',
                        'path' => $attachmentPath,
                        'mime_type' => $upload->getMimeType(),
                        'size_bytes' => $upload->getSize(),
                    ]);
                }

                return $ticket;
            });
        } catch (Throwable $exception) {
            if ($attachmentPath) {
                Storage::disk('private')->delete($attachmentPath);
            }

            throw $exception;
        }

        $request->session()->flash('submitted_ticket', [
            'ticket_number' => $ticket->ticket_number,
            'submitted_at' => $ticket->created_at->format('F j, Y g:i A'),
            'status' => $ticket->status,
        ]);

        return to_route('ticket.submitted');
    }

    public function submitted(Request $request): View|RedirectResponse
    {
        $submittedTicket = $request->session()->get('submitted_ticket');

        if (! is_array($submittedTicket)) {
            return to_route('ticket.create');
        }

        return view('pages.ticket-submitted', ['submittedTicket' => $submittedTicket]);
    }

    public function lookup(Request $request): View
    {
        return view('pages.check-ticket', [
            'ticket' => null,
            'lookupAttempted' => false,
            'rememberedEmail' => $request->session()->get('ticket_lookup_email', ''),
            'lookupTicketNumber' => '',
            'lookupEmail' => '',
        ]);
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

        $ticket = Ticket::query()
            ->with([
                'department',
                'category',
                'comments' => fn ($query) => $query->where('is_internal', false)->oldest(),
                'attachments',
            ])
            ->where('ticket_number', $validated['ticket'])
            ->whereRaw('LOWER(requester_email) = ?', [Str::lower($lookupEmail)])
            ->first();

        return view('pages.check-ticket', [
            'ticket' => $ticket,
            'lookupAttempted' => true,
            'rememberedEmail' => $rememberedEmail,
            'lookupTicketNumber' => $validated['ticket'],
            'lookupEmail' => $lookupEmail,
        ]);
    }

    public function download(LookupTicketRequest $request, TicketAttachment $attachment): StreamedResponse
    {
        $validated = $request->validated();
        $belongsToVerifiedTicket = $attachment->ticket()
            ->where('ticket_number', $validated['ticket'])
            ->whereRaw('LOWER(requester_email) = ?', [Str::lower($validated['email'])])
            ->exists();

        abort_unless($belongsToVerifiedTicket && $attachment->disk === 'private', 404);

        $disk = Storage::disk('private');
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->download($attachment->path, $attachment->original_name);
    }
}
