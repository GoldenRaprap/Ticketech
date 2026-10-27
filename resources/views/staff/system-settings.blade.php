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

    @if (session('status'))
        <div class="workspace-alert workspace-alert--success" role="status">{{ session('status') }}</div>
    @endif

    <section class="admin-system-status" aria-label="System status">
        <article><span class="admin-system-status-icon admin-system-status-icon--green" aria-hidden="true">✓</span><div><strong>Helpdesk portal</strong><small>Public ticket submission available</small></div><span class="admin-access-state admin-access-state--active"><span></span>Online</span></article>
        <article><span class="admin-system-status-icon admin-system-status-icon--blue" aria-hidden="true">✉</span><div><strong>Outgoing email</strong><small>{{ strtoupper(config('mail.default')) }}{{ config('mail.default') === 'smtp' ? ' · '.config('mail.mailers.smtp.host') : '' }}</small></div><span class="admin-readonly-badge">{{ $mailTransportStatus }}</span></article>
        <article><span class="admin-system-status-icon admin-system-status-icon--amber" aria-hidden="true">◷</span><div><strong>Time zone</strong><small>Asia/Manila</small></div><span class="admin-readonly-badge">Local time</span></article>
    </section>

    <div class="admin-settings-layout">
        <div class="admin-settings-main">
            <section class="admin-console-card">
                <header class="admin-console-card-heading">
                    <div><span class="admin-page-eyebrow">EMAIL DELIVERY</span><h2>Email sender settings</h2><p>These details appear on outgoing verification and password emails.</p></div>
                </header>
                <p>Configure the SMTP connection here. The app password is encrypted before it is saved and is never displayed after saving.</p>

                @if ($errors->any())
                    <div class="workspace-alert workspace-alert--error" role="alert">
                        <strong>Unable to update email settings:</strong>
                        <ul>
                            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                <form class="admin-staff-create-form admin-mail-settings-form" action="{{ route('admin.system-settings.update') }}" method="post">
                    @csrf
                    @method('put')
                    <label class="admin-staff-field" for="mail-from-address">
                        <span>Sender email address</span>
                        <input id="mail-from-address" name="from_address" type="email" value="{{ old('from_address', $fromAddress) }}" autocomplete="email" maxlength="255" required>
                    </label>
                    <label class="admin-staff-field" for="mail-from-name">
                        <span>Sender name</span>
                        <input id="mail-from-name" name="from_name" value="{{ old('from_name', $fromName) }}" maxlength="255" required>
                    </label>
                    <label class="admin-staff-field" for="smtp-host">
                        <span>SMTP host</span>
                        <input id="smtp-host" name="smtp_host" value="{{ old('smtp_host', $smtpHost) }}" placeholder="smtp.gmail.com" maxlength="255" required>
                    </label>
                    <label class="admin-staff-field" for="smtp-port">
                        <span>SMTP port</span>
                        <input id="smtp-port" name="smtp_port" type="number" value="{{ old('smtp_port', $smtpPort) }}" min="1" max="65535" required>
                    </label>
                    <label class="admin-staff-field" for="smtp-encryption">
                        <span>Connection security</span>
                        <select id="smtp-encryption" name="smtp_encryption" required>
                            <option value="tls" @selected(old('smtp_encryption', $smtpEncryption) === 'tls')>STARTTLS (recommended · port 587)</option>
                            <option value="ssl" @selected(old('smtp_encryption', $smtpEncryption) === 'ssl')>SSL/TLS (port 465)</option>
                            <option value="none" @selected(old('smtp_encryption', $smtpEncryption) === 'none')>None (not recommended)</option>
                        </select>
                    </label>
                    <label class="admin-staff-field" for="smtp-username">
                        <span>SMTP username</span>
                        <input id="smtp-username" name="smtp_username" type="text" value="{{ old('smtp_username', $smtpUsername) }}" autocomplete="username" maxlength="255" required>
                    </label>
                    <label class="admin-staff-field" for="smtp-app-password">
                        <span>SMTP app password</span>
                        <input id="smtp-app-password" name="smtp_app_password" type="password" autocomplete="new-password" placeholder="{{ $hasSmtpAppPassword ? 'Leave blank to keep the saved password' : 'Enter your SMTP app password' }}" maxlength="512">
                        <small>{{ $hasSmtpAppPassword ? 'A password is configured. Enter a new value only to replace it.' : 'Required to authenticate with the SMTP server. For Gmail, use an App Password rather than your regular account password.' }}</small>
                    </label>
                    @if ($hasSmtpAppPassword)
                        <label class="admin-setting-row" for="clear-smtp-app-password">
                            <input id="clear-smtp-app-password" name="clear_smtp_app_password" type="checkbox" value="1">
                            <span><strong>Remove saved app password</strong><small>Revert to the application environment password, if configured.</small></span>
                        </label>
                    @endif
                    <button class="admin-console-button" type="submit">Save email and SMTP settings</button>
                </form>
            </section>

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
                <p class="admin-security-footnote">SMTP passwords are stored encrypted. They are write-only in this form and are never included in the settings page.</p>
            </section>
        </aside>
    </div>
</div>
@endsection
