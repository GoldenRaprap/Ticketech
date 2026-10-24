<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentPreviewController extends Controller
{
    public function requester(Request $request, TicketAttachment $attachment): StreamedResponse
    {
        $lookupContext = $request->session()->get('ticket_lookup_context');

        abort_unless(
            is_array($lookupContext)
                && is_string($lookupContext['ticket'] ?? null)
                && is_string($lookupContext['email'] ?? null),
            404,
        );

        $belongsToVerifiedTicket = $attachment->ticket()
            ->where('ticket_number', $lookupContext['ticket'])
            ->whereRaw('LOWER(requester_email) = ?', [Str::lower($lookupContext['email'])])
            ->exists();

        abort_unless($belongsToVerifiedTicket, 404);

        if ($attachment->comment_id !== null) {
            abort_unless(
                $attachment->comment()
                    ->where('ticket_id', $attachment->ticket_id)
                    ->where('is_internal', false)
                    ->exists(),
                404,
            );
        }

        return $this->preview($attachment);
    }

    public function staff(Ticket $ticket, TicketAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->ticket_id === $ticket->id, 404);

        return $this->preview($attachment);
    }

    private function preview(TicketAttachment $attachment): StreamedResponse
    {
        $previewableMimeTypes = ['image/jpeg', 'image/png', 'application/pdf'];

        abort_unless(
            $attachment->disk === 'private'
                && in_array($attachment->mime_type, $previewableMimeTypes, true),
            404,
        );

        $disk = Storage::disk('private');
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->response($attachment->path, $attachment->original_name, [
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:; object-src 'none'; base-uri 'none'",
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
