<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket {{ $ticket->ticket_number }}</title>
    <style>
        :root { color-scheme: light; font-family: Arial, sans-serif; color: #173b63; }
        body { max-width: 760px; margin: 40px auto; padding: 0 24px; line-height: 1.55; }
        h1, h2 { color: #123d72; }
        h1 { margin-bottom: 4px; }
        h2 { margin-top: 28px; border-bottom: 1px solid #d5e0ec; padding-bottom: 6px; }
        .eyebrow { color: #55718e; font-size: 0.8rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }
        .ticket-number { display: inline-block; padding: 8px 12px; border: 1px solid #d5e0ec; border-radius: 6px; background: #f3f7fb; font-weight: 700; }
        dl { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        dl div { padding: 12px; border: 1px solid #d5e0ec; border-radius: 6px; }
        dt { color: #607890; font-size: 0.8rem; }
        dd { margin: 4px 0 0; font-weight: 600; overflow-wrap: anywhere; }
        .message { padding: 12px 0; border-bottom: 1px solid #e4eaf0; }
        .message p { white-space: pre-wrap; overflow-wrap: anywhere; }
        .message-meta { color: #607890; font-size: 0.85rem; }
        ul { padding-left: 20px; }
        @media (max-width: 560px) { dl { grid-template-columns: 1fr; } }
        @media print { body { max-width: none; margin: 0; padding: 0; } }
    </style>
</head>
<body>
    <p class="eyebrow">PCU Helpdesk · Support request</p>
    <h1>{{ $ticket->subject }}</h1>
    <p class="ticket-number">{{ $ticket->ticket_number }}</p>

    <dl>
        <div><dt>Requester</dt><dd>{{ $ticket->requester_name }}</dd></div>
        <div><dt>Student or employee ID</dt><dd>{{ $ticket->student_id }}</dd></div>
        <div><dt>Email</dt><dd>{{ $ticket->requester_email }}</dd></div>
        <div><dt>Phone</dt><dd>{{ $ticket->requester_phone ?: 'Not provided' }}</dd></div>
        <div><dt>Date submitted</dt><dd>{{ $ticket->created_at->copy()->timezone(config('app.display_timezone'))->format('F j, Y g:i A') }}</dd></div>
        <div><dt>Status</dt><dd>{{ $ticket->status }}</dd></div>
        <div><dt>Priority</dt><dd>{{ $ticket->priority }}</dd></div>
    </dl>

    <h2>Conversation</h2>
    <div class="message">
        <strong>{{ $ticket->requester_name }}</strong>
        <span class="message-meta"> · Original request · {{ $ticket->created_at->copy()->timezone(config('app.display_timezone'))->format('M j, Y g:i A') }}</span>
        <p>{{ $ticket->description }}</p>
        @if ($ticket->attachments->isNotEmpty())
            <ul>
                @foreach ($ticket->attachments as $attachment)
                    <li>{{ $attachment->original_name }}</li>
                @endforeach
            </ul>
        @endif
    </div>
    @foreach ($ticket->comments as $comment)
        <div class="message">
            @php($isRequesterComment = $comment->author_id === null && $comment->author_name === $ticket->requester_name)
            <strong>{{ $isRequesterComment ? 'You' : 'PCU IT Support' }}</strong>
            <span class="message-meta"> · {{ $comment->created_at->copy()->timezone(config('app.display_timezone'))->format('M j, Y g:i A') }}</span>
            <p>{{ $comment->body }}</p>
            @if ($comment->attachments->isNotEmpty())
                <ul>
                    @foreach ($comment->attachments as $attachment)
                        <li>{{ $attachment->original_name }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endforeach
    @if ($autoPrint)
        <script>
            window.addEventListener('load', () => window.print());
        </script>
    @endif
</body>
</html>
