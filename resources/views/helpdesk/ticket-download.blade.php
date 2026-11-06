<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket {{ $document['ticket_number'] }} | PCU Helpdesk</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Arial, Helvetica, sans-serif;
            color: #233d5b;
            background: #edf3fa;
        }

        * { box-sizing: border-box; }

        body {
            width: min(900px, calc(100% - 40px));
            margin: 36px auto;
            padding: 30px;
            border: 1px solid #dce6f1;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 16px 42px rgba(27, 55, 88, 0.12);
            line-height: 1.55;
        }

        .document-header {
            padding: 22px 24px;
            border-radius: 12px;
            background: #123f78;
            color: #fff;
        }

        .eyebrow {
            margin: 0 0 8px;
            color: #c4d9f3;
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0;
            font-size: clamp(1.45rem, 3vw, 2rem);
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .ticket-number {
            display: inline-flex;
            margin-top: 16px;
            padding: 6px 11px;
            border: 1px solid rgba(255,255,255,0.42);
            border-radius: 999px;
            color: #fff;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        .submitted-at {
            margin: 13px 0 0;
            color: #e0ebf8;
            font-size: 0.84rem;
        }

        .document-section { margin-top: 22px; }

        h2 {
            margin: 0 0 10px;
            color: #345779;
            font-size: 0.74rem;
            font-weight: 750;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .document-details {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .detail-group {
            min-width: 0;
            padding: 15px 17px;
            border: 1px solid #dce6f1;
            border-radius: 11px;
            background: #f7faff;
        }

        .ticket-details {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px 18px;
            margin: 0;
        }

        .ticket-details > div {
            min-width: 0;
        }

        dt {
            margin-bottom: 3px;
            color: #6a7e93;
            font-size: 0.65rem;
            font-weight: 600;
        }

        dd {
            margin: 0;
            color: #173d68;
            font-size: 0.76rem;
            font-weight: 650;
            overflow-wrap: anywhere;
            white-space: pre-wrap;
        }

        .ticket-status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 5px 9px;
            border-radius: 999px;
            background: #edf3fa;
            color: #38536f;
            font-size: 0.74rem;
        }

        .ticket-status::before {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
            content: "";
        }

        .ticket-status--resolved,
        .ticket-status--closed {
            background: #e9f7ef;
            color: #247248;
        }

        .ticket-status--in-progress,
        .ticket-status--assigned {
            background: #eaf2ff;
            color: #285d9f;
        }

        .ticket-status--waiting-for-user,
        .ticket-status--on-hold {
            background: #fff5e4;
            color: #8a5a14;
        }

        .conversation-entry {
            padding: 12px 0;
            border-top: 1px solid #dce6f1;
        }

        .conversation-entry:last-child { border-bottom: 1px solid #dce6f1; }

        .message-heading {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 4px 9px;
        }

        .message-heading strong {
            color: #234b79;
            font-size: 0.86rem;
        }

        .message-context,
        time {
            color: #72859a;
            font-size: 0.74rem;
        }

        .conversation-entry > p {
            margin: 8px 0 0;
            color: #354d68;
            font-size: 0.84rem;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .attachments {
            margin: 10px 0 0;
            padding-left: 20px;
            color: #4a6682;
            font-size: 0.8rem;
        }

        .document-footer {
            margin-top: 22px;
            padding-top: 12px;
            border-top: 1px solid #e2e9f1;
            color: #71849a;
            font-size: 0.76rem;
            text-align: center;
        }

        @media (max-width: 560px) {
            body { width: calc(100% - 20px); margin: 10px auto; padding: 16px; }
            .document-details { grid-template-columns: 1fr; }
            .document-header { padding: 18px; }
            .detail-group { padding: 13px; }
        }

        @media print {
            :root { background: #fff; }
            body {
                width: auto;
                margin: 0;
                padding: 0;
                border: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .document-header {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            .detail-group {
                break-inside: avoid;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <header class="document-header">
        <p class="eyebrow">PCU Helpdesk · Support request</p>
        <h1>{{ $document['subject'] }}</h1>
        <span class="ticket-number">{{ $document['ticket_number'] }}</span>
        <p class="submitted-at">Submitted {{ $document['submitted_at'] }}</p>
    </header>

    <div class="document-section document-details">
        @foreach ($document['detail_groups'] as $group)
            <section class="detail-group" aria-label="{{ $group['title'] }}">
                <h2>{{ $group['title'] }}</h2>
                <dl class="ticket-details">
                    @foreach ($group['details'] as $detail)
                        <div>
                            <dt>{{ $detail['label'] }}</dt>
                            <dd>
                                @if ($detail['label'] === 'Current status')
                                    <span class="ticket-status ticket-status--{{ \Illuminate\Support\Str::slug($detail['value']) }}">{{ $detail['value'] }}</span>
                                @else
                                    {{ $detail['value'] }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endforeach
    </div>

    <section class="document-section" aria-labelledby="ticket-conversation-title">
        <h2 id="ticket-conversation-title">Ticket conversation</h2>
        @foreach ($document['messages'] as $message)
            <article class="conversation-entry">
                <div class="message-heading">
                    <strong>{{ $message['author'] }}</strong>
                    <span class="message-context">{{ $message['context'] }}</span>
                    <time>{{ $message['created_at'] }}</time>
                </div>
                <p>{{ $message['body'] }}</p>
                @if ($message['attachments'] !== [])
                    <ul class="attachments">
                        @foreach ($message['attachments'] as $attachment)
                            <li>{{ $attachment }}</li>
                        @endforeach
                    </ul>
                @endif
            </article>
        @endforeach
    </section>

    <footer class="document-footer">PCU Student &amp; Faculty Support · Check your ticket status at {{ route('ticket.check') }}</footer>

    @if ($autoPrint)
        <script>
            window.addEventListener('load', () => window.print());
        </script>
    @endif
</body>
</html>
