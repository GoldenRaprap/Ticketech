@extends('layouts.staff')
@section('title', 'Notifications')
@section('content')
<div class="workspace-page notifications-page">
    @if (session('status'))
        <div class="workspace-alert workspace-alert--success" role="status">{{ session('status') }}</div>
    @endif
    <header class="workspace-page-heading">
        <div>
            <span class="workspace-eyebrow">Stay up to date</span>
            <h1>Notifications</h1>
            <p>Important updates about tickets and activity in your workspace.</p>
        </div>
        @if ($unreadCount > 0)
            <form action="{{ route('staff.notifications.read-all') }}" method="post">
                @csrf
                @method('patch')
                <button class="workspace-button workspace-button--secondary" type="submit">Mark all as read</button>
            </form>
        @endif
    </header>

    <section class="notification-summary">
        <span class="notification-summary-icon" aria-hidden="true">♧</span>
        <div><strong>{{ $unreadCount }} unread {{ \Illuminate\Support\Str::plural('notification', $unreadCount) }}</strong><p>Notifications are generated from new tickets, assignments, replies, and client reports.</p></div>
        <span class="notification-unread-count">{{ $notifications->total() }} total</span>
    </section>

    <section class="workspace-card notification-card">
        <div class="workspace-card-heading notification-list-heading">
            <div><h2>Recent activity</h2><p>Your latest ticket updates.</p></div>
            <form method="get" action="{{ route('staff.notifications') }}">
                <label class="sr-only" for="notification-filter">Filter notifications</label>
                <select id="notification-filter" name="read" onchange="this.form.submit()">
                    <option value="all" @selected($filter === 'all')>All updates</option>
                    <option value="unread" @selected($filter === 'unread')>Unread</option>
                    <option value="read" @selected($filter === 'read')>Read</option>
                </select>
            </form>
        </div>
        <div class="notification-list">
            @forelse ($notifications as $notification)
                <article class="notification-item {{ $notification->read_at ? '' : 'notification-item--unread' }}" @if ($notification->ticket) data-notification-row role="button" tabindex="0" aria-label="Open notification: {{ $notification->title }}" @endif>
                    <span class="notification-icon notification-icon--{{ $notification->type }}" aria-hidden="true">
                        @if ($notification->type === 'assignment') ↗ @elseif ($notification->type === 'reply' || $notification->type === 'requester_reply') ↩ @elseif ($notification->type === 'new_ticket') ＋ @elseif ($notification->type === 'client_report') ⚑ @else ✓ @endif
                    </span>
                    <div class="notification-copy">
                        <div class="notification-title-row"><h3>{{ $notification->title }}</h3>@unless ($notification->read_at)<span class="unread-label">New</span>@endunless</div>
                        <p>{{ $notification->message }}</p>
                    </div>
                    <time datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->diffForHumans() }}</time>
                    @if ($notification->ticket)
                        <form data-notification-action action="{{ route('staff.notifications.read', $notification) }}" method="post" hidden>
                            @csrf
                            @method('patch')
                        </form>
                    @else
                        <span class="notification-unread-dot" @if ($notification->read_at) hidden @else aria-label="Unread" @endif></span>
                    @endif
                </article>
            @empty
                <div class="workspace-empty-state"><span class="workspace-empty-icon" aria-hidden="true">✓</span><strong>No notifications here</strong><p>New assignments, ticket replies, and client reports will appear here.</p></div>
            @endforelse
        </div>
        <footer class="table-footer"><span>Showing {{ $notifications->count() }} of {{ $notifications->total() }} notifications</span>{{ $notifications->links() }}</footer>
    </section>
</div>
@endsection
