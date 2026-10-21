@extends('layouts.staff')
@section('title', 'System Settings')
@section('heading', 'System Settings')
@section('content')
<div class="admin-console-page">
    <header class="admin-page-header">
        <div>
            <span class="admin-page-eyebrow">ADMINISTRATION / CONFIGURATION</span>
            <h1>System settings</h1>
            <p>Review the identity, public access, and security posture of the helpdesk.</p>
        </div>
        <span class="admin-page-context"><span aria-hidden="true">●</span> System overview</span>
    </header>

    <section class="admin-system-status" aria-label="System status">
        <article><span class="admin-system-status-icon admin-system-status-icon--green" aria-hidden="true">✓</span><div><strong>Helpdesk portal</strong><small>Public ticket submission available</small></div><span class="admin-access-state admin-access-state--active"><span></span>Online</span></article>
        <article><span class="admin-system-status-icon admin-system-status-icon--blue" aria-hidden="true">✉</span><div><strong>Support identity</strong><small>PCU IT Support</small></div><span class="admin-readonly-badge">Configured</span></article>
        <article><span class="admin-system-status-icon admin-system-status-icon--amber" aria-hidden="true">◷</span><div><strong>Time zone</strong><small>Asia/Manila</small></div><span class="admin-readonly-badge">Local time</span></article>
    </section>

    <div class="admin-settings-layout">
        <div class="admin-settings-main">
            <section class="admin-console-card">
                <header class="admin-console-card-heading">
                    <div><span class="admin-page-eyebrow">ORGANIZATION</span><h2>Helpdesk identity</h2><p>These details identify the service to PCU students and employees.</p></div>
                    <span class="admin-readonly-badge">Read only</span>
                </header>
                <dl class="admin-config-list">
                    <div><dt>System name</dt><dd>Ticketech</dd></div>
                    <div><dt>Service description</dt><dd>School IT Helpdesk and Ticketing System</dd></div>
                    <div><dt>Support team</dt><dd>PCU IT Support</dd></div>
                    <div><dt>Time zone</dt><dd>Asia/Manila (UTC+08:00)</dd></div>
                </dl>
            </section>

            <section class="admin-console-card">
                <header class="admin-console-card-heading">
                    <div><span class="admin-page-eyebrow">PUBLIC EXPERIENCE</span><h2>Helpdesk availability</h2><p>Current public-facing capabilities.</p></div>
                </header>
                <div class="admin-setting-row"><span class="admin-setting-icon" aria-hidden="true">＋</span><span><strong>Ticket submission</strong><small>Visitors can submit a support request without an account.</small></span><span class="admin-toggle-state"><span></span>Enabled</span></div>
                <div class="admin-setting-row"><span class="admin-setting-icon" aria-hidden="true">▤</span><span><strong>Knowledge base</strong><small>Published self-service articles are available to visitors.</small></span><span class="admin-toggle-state"><span></span>Enabled</span></div>
                <div class="admin-setting-row"><span class="admin-setting-icon" aria-hidden="true">↩</span><span><strong>Ticket replies</strong><small>Requesters can continue their verified ticket conversation.</small></span><span class="admin-toggle-state"><span></span>Enabled</span></div>
            </section>
        </div>

        <aside class="admin-settings-aside">
            <section class="admin-console-card admin-security-card">
                <span class="admin-page-eyebrow">ACCESS CONTROL</span>
                <h2>Role boundaries</h2>
                <div class="admin-role-summary"><span class="admin-role-icon admin-role-icon--admin" aria-hidden="true">♙</span><div><strong>Administrator</strong><p>Moderation, audit logs, and console access.</p></div></div>
                <div class="admin-role-summary"><span class="admin-role-icon" aria-hidden="true">♙</span><div><strong>Helpdesk staff</strong><p>Ticket handling, replies, and client reporting.</p></div></div>
                <p class="admin-security-footnote">System options are presented as a status overview; configuration editing is not enabled.</p>
            </section>
        </aside>
    </div>
</div>
@endsection
