@if ($attachments->isNotEmpty())
    <div class="message-attachments" aria-label="Message attachments">
        @foreach ($attachments as $attachment)
            @php($previewUrl = route($previewRouteName, $staffView ? [$ticket, $attachment] : $attachment))
            @php($isImage = in_array($attachment->mime_type, ['image/jpeg', 'image/png'], true))
            @php($isPdf = $attachment->mime_type === 'application/pdf')
            <article class="message-attachment">
                @if ($isImage)
                    <button class="message-attachment-preview" type="button" data-image-preview data-preview-src="{{ $previewUrl }}" data-preview-alt="{{ $attachment->original_name }}">
                        <img src="{{ $previewUrl }}" alt="Preview of {{ $attachment->original_name }}" loading="lazy">
                        <span>Click to enlarge</span>
                    </button>
                @elseif ($isPdf)
                    <iframe class="message-attachment-pdf" src="{{ $previewUrl }}" title="Preview of {{ $attachment->original_name }}" loading="lazy"></iframe>
                    <a class="message-attachment-open" href="{{ $previewUrl }}" target="_blank" rel="noopener">Open PDF preview</a>
                @else
                    <span class="message-attachment-file-icon" aria-hidden="true">▤</span>
                @endif

                <div class="message-attachment-details">
                    <strong>{{ $attachment->original_name }}</strong>
                    <small>{{ $attachment->size_bytes ? number_format($attachment->size_bytes / 1024, 0).' KB' : 'Attachment' }}</small>
                </div>
                @if ($staffView)
                    <a class="message-attachment-download" href="{{ route('staff.ticket-attachments.download', [$ticket, $attachment]) }}">Download</a>
                @else
                    <form class="message-attachment-download" action="{{ route('ticket.attachments.download', $attachment) }}" method="post">
                        @csrf
                        <input type="hidden" name="ticket" value="{{ $lookupTicketNumber }}">
                        <input type="hidden" name="email" value="{{ $lookupEmail }}">
                        <button type="submit">Download</button>
                    </form>
                @endif
            </article>
        @endforeach
    </div>
@endif
