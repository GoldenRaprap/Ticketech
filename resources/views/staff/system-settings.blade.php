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
        <article><span class="admin-system-status-icon admin-system-status-icon--{{ $ticketSubmissionOpen ? 'green' : 'amber' }}" aria-hidden="true">{{ $ticketSubmissionOpen ? '✓' : '!' }}</span><div><strong>Helpdesk portal</strong><small>{{ $ticketSubmissionOpen ? 'Public ticket submission available' : 'Public ticket submission paused' }}</small></div><span class="admin-access-state {{ $ticketSubmissionOpen ? 'admin-access-state--active' : 'admin-access-state--inactive' }}"><span></span>{{ $ticketSubmissionOpen ? 'Online' : 'Maintenance' }}</span></article>
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

                @if (filled($mailDeliveryFailureMessage))
                    <div class="workspace-alert workspace-alert--error" role="alert">
                        <strong>Automatic email delivery needs attention.</strong>
                        <p>{{ $mailDeliveryFailureMessage }}</p>
                        <p>Ticket submissions and replies are paused. After correcting the mail settings and confirming delivery works, re-enable them in Helpdesk availability below.</p>
                    </div>
                @endif

                @if ($errors->hasAny(['from_address', 'from_name', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_app_password', 'clear_smtp_app_password']))
                    <div class="workspace-alert workspace-alert--error" role="alert">
                        <strong>Unable to update email settings:</strong>
                        <ul>@foreach ($errors->only(['from_address', 'from_name', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_app_password', 'clear_smtp_app_password']) as $error)<li>{{ $error }}</li>@endforeach</ul>
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
                    <div><span class="admin-page-eyebrow">BOT PROTECTION</span><h2>Cloudflare Turnstile</h2><p>Manage the CAPTCHA keys used by public ticket submissions.</p></div>
                </header>
                <p>The site key is shown on the public form. The secret key is encrypted before saving and is never displayed. Both keys must be configured for ticket submissions to use Turnstile.</p>
                <p role="status">Status: {{ $turnstileConfigured ? 'Configured' : 'Credentials needed' }}</p>

                @if ($errors->hasAny(['turnstile_site_key', 'turnstile_secret_key', 'clear_turnstile_site_key', 'clear_turnstile_secret_key']))
                    <div class="workspace-alert workspace-alert--error" role="alert">
                        <strong>Unable to update Turnstile settings:</strong>
                        <ul>@foreach ($errors->only(['turnstile_site_key', 'turnstile_secret_key', 'clear_turnstile_site_key', 'clear_turnstile_secret_key']) as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <form class="admin-staff-create-form" action="{{ route('admin.system-settings.turnstile.update') }}" method="post">
                    @csrf
                    @method('put')
                    <label class="admin-staff-field" for="turnstile-site-key">
                        <span>Turnstile site key</span>
                        <input id="turnstile-site-key" name="turnstile_site_key" value="{{ old('turnstile_site_key', $turnstileSiteKey) }}" autocomplete="off" maxlength="255">
                        <small>{{ $hasTurnstileSiteKey ? 'A site key is configured. A saved system setting takes precedence over the environment value.' : 'Enter the site key from your Cloudflare Turnstile widget.' }}</small>
                    </label>
                    <label class="admin-staff-field" for="turnstile-secret-key">
                        <span>Turnstile secret key</span>
                        <input id="turnstile-secret-key" name="turnstile_secret_key" type="password" autocomplete="new-password" placeholder="{{ $hasTurnstileSecret ? 'Leave blank to keep the current secret' : 'Enter your Turnstile secret key' }}" maxlength="512">
                        <small>{{ $hasSavedTurnstileSecret ? 'A secret is saved. Enter a new value only to replace it.' : ($hasTurnstileSecret ? 'A secret is configured through the environment. Enter a value only to override it here.' : 'Enter the secret key from your Cloudflare Turnstile widget.') }}</small>
                    </label>
                    @if ($hasSavedTurnstileSiteKey)
                        <label class="admin-setting-row" for="clear-turnstile-site-key">
                            <input id="clear-turnstile-site-key" name="clear_turnstile_site_key" type="checkbox" value="1">
                            <span><strong>Remove saved site key</strong><small>Revert to the environment value, if configured.</small></span>
                        </label>
                    @endif
                    @if ($hasSavedTurnstileSecret)
                        <label class="admin-setting-row" for="clear-turnstile-secret-key">
                            <input id="clear-turnstile-secret-key" name="clear_turnstile_secret_key" type="checkbox" value="1">
                            <span><strong>Remove saved secret key</strong><small>Revert to the environment value, if configured.</small></span>
                        </label>
                    @endif
                    <button class="admin-console-button" type="submit">Save Turnstile settings</button>
                </form>
            </section>

            <section class="admin-console-card">
                <header class="admin-console-card-heading">
                    <div><span class="admin-page-eyebrow">SERVICE HOURS</span><h2>Resolution target business hours</h2><p>Set the local opening and closing times used to calculate resolution deadlines. Business days remain Monday to Friday; public holidays are not excluded.</p></div>
                </header>
                @if ($errors->hasAny(['opening_time', 'closing_time']))
                    <div class="workspace-alert workspace-alert--error" role="alert">
                        <strong>Unable to update business hours:</strong>
                        <ul>@foreach ($errors->only(['opening_time', 'closing_time']) as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                <form class="admin-staff-create-form" action="{{ route('admin.system-settings.business-hours.update') }}" method="post">
                    @csrf
                    @method('put')
                    <div class="admin-staff-field business-time-picker-field" data-business-time-picker>
                        <span id="business-opening-time-label">Opening time</span>
                        <input
                            id="business-opening-time"
                            name="opening_time"
                            type="time"
                            value="{{ old('opening_time', $businessHours['opening_time']) }}"
                            aria-labelledby="business-opening-time-label"
                            data-time-source
                            required
                        >
                        <button
                            class="business-time-picker-trigger"
                            type="button"
                            aria-labelledby="business-opening-time-label business-opening-time-display"
                            aria-controls="business-opening-time-picker-panel"
                            aria-expanded="false"
                            hidden
                            data-time-trigger
                        >
                            <span id="business-opening-time-display" data-time-display></span>
                            <span class="business-time-picker-icon" aria-hidden="true">◷</span>
                        </button>
                        <div
                            id="business-opening-time-picker-panel"
                            class="business-time-picker-panel"
                            role="dialog"
                            aria-label="Choose opening time"
                            hidden
                            data-time-panel
                        >
                            <p>Select an hour, minute, and period.</p>
                            <div class="business-time-picker-controls">
                                <label>
                                    <span>Hour</span>
                                    <select data-time-hour required>
                                        <option value="" disabled selected>Hour</option>
                                        @foreach (range(1, 12) as $hour)
                                            <option value="{{ sprintf('%02d', $hour) }}">{{ $hour }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>
                                    <span>Minute</span>
                                    <select data-time-minute required>
                                        <option value="" disabled selected>Min</option>
                                        @foreach (range(0, 59) as $minute)
                                            <option value="{{ sprintf('%02d', $minute) }}">{{ sprintf('%02d', $minute) }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>
                                    <span>Period</span>
                                    <select data-time-period required>
                                        <option value="" disabled selected>AM/PM</option>
                                        <option value="AM">AM</option>
                                        <option value="PM">PM</option>
                                    </select>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="admin-staff-field business-time-picker-field business-time-picker-field--align-end" data-business-time-picker>
                        <span id="business-closing-time-label">Closing time</span>
                        <input
                            id="business-closing-time"
                            name="closing_time"
                            type="time"
                            value="{{ old('closing_time', $businessHours['closing_time']) }}"
                            aria-labelledby="business-closing-time-label"
                            data-time-source
                            required
                        >
                        <button
                            class="business-time-picker-trigger"
                            type="button"
                            aria-labelledby="business-closing-time-label business-closing-time-display"
                            aria-controls="business-closing-time-picker-panel"
                            aria-expanded="false"
                            hidden
                            data-time-trigger
                        >
                            <span id="business-closing-time-display" data-time-display></span>
                            <span class="business-time-picker-icon" aria-hidden="true">◷</span>
                        </button>
                        <div
                            id="business-closing-time-picker-panel"
                            class="business-time-picker-panel"
                            role="dialog"
                            aria-label="Choose closing time"
                            hidden
                            data-time-panel
                        >
                            <p>Select an hour, minute, and period.</p>
                            <div class="business-time-picker-controls">
                                <label>
                                    <span>Hour</span>
                                    <select data-time-hour required>
                                        <option value="" disabled selected>Hour</option>
                                        @foreach (range(1, 12) as $hour)
                                            <option value="{{ sprintf('%02d', $hour) }}">{{ $hour }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>
                                    <span>Minute</span>
                                    <select data-time-minute required>
                                        <option value="" disabled selected>Min</option>
                                        @foreach (range(0, 59) as $minute)
                                            <option value="{{ sprintf('%02d', $minute) }}">{{ sprintf('%02d', $minute) }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>
                                    <span>Period</span>
                                    <select data-time-period required>
                                        <option value="" disabled selected>AM/PM</option>
                                        <option value="AM">AM</option>
                                        <option value="PM">PM</option>
                                    </select>
                                </label>
                            </div>
                        </div>
                    </div>
                    <button class="admin-console-button" type="submit">Save business hours</button>
                </form>
            </section>

            <section class="admin-console-card">
                <header class="admin-console-card-heading">
                    <div><span class="admin-page-eyebrow">PUBLIC EXPERIENCE</span><h2>Helpdesk availability</h2><p>Current public-facing capabilities.</p></div>
                </header>
                @if ($errors->hasAny(['submission_enabled', 'submission_message', 'knowledge_base_enabled', 'knowledge_base_message', 'ticket_replies_enabled', 'ticket_replies_message']))
                    <div class="workspace-alert workspace-alert--error" role="alert">
                        <strong>Unable to update helpdesk availability settings:</strong>
                        <ul>
                            @foreach ($errors->only(['submission_enabled', 'submission_message', 'knowledge_base_enabled', 'knowledge_base_message', 'ticket_replies_enabled', 'ticket_replies_message']) as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <form class="admin-staff-create-form admin-ticket-submission-form" action="{{ route('admin.system-settings.ticket-submission.update') }}" method="post">
                    @csrf
                    @method('put')
                    <input type="hidden" name="submission_enabled" value="0">
                    <details class="admin-ticket-message-details" @if ($errors->hasAny(['submission_enabled', 'submission_message'])) open @endif>
                        <summary class="admin-ticket-submission-summary">
                            <span class="admin-setting-icon" aria-hidden="true">＋</span>
                            <span class="admin-ticket-submission-copy">
                                <strong>Ticket submission</strong>
                                <small data-public-feature-description>{{ $ticketSubmissionOpen ? 'Visitors can submit a support request without an account.' : 'New ticket submissions are paused. Existing tickets remain available.' }}</small>
                            </span>
                            <label class="admin-ticket-submission-switch" for="ticket-submission-enabled">
                                <span class="admin-switch-control">
                                    <input id="ticket-submission-enabled" name="submission_enabled" type="checkbox" value="1" role="switch" aria-label="Enable ticket submissions" data-public-feature-toggle data-disable-title="Disable ticket submissions?" data-enabled-description="Visitors can submit a support request without an account." data-disabled-description="New ticket submissions are paused. Existing tickets remain available." data-disable-confirmation="Visitors will not be able to create new tickets." @checked((string) old('submission_enabled', $ticketSubmissionOpen ? '1' : '0') === '1')>
                                    <span class="admin-switch-track" aria-hidden="true"></span>
                                </span>
                            </label>
                            <span class="admin-ticket-submission-switch-state {{ $ticketSubmissionOpen ? '' : 'admin-access-state--inactive' }}" data-public-feature-state>{{ $ticketSubmissionOpen ? 'Enabled' : 'Disabled' }}</span>
                            <span class="admin-ticket-message-chevron-button" aria-hidden="true">
                                <span class="admin-ticket-message-chevron"></span>
                            </span>
                        </summary>
                        <div class="admin-ticket-message-content">
                            <label class="admin-staff-field" for="ticket-submission-message">
                                <span>Message shown while submissions are paused</span>
                                <textarea id="ticket-submission-message" name="submission_message" rows="3" maxlength="500" required>{{ old('submission_message', $ticketSubmissionMessage) }}</textarea>
                            </label>
                            <button class="admin-console-button" type="submit">Save ticket availability</button>
                        </div>
                    </details>
                </form>
                <form class="admin-staff-create-form admin-ticket-submission-form" action="{{ route('admin.system-settings.knowledge-base.update') }}" method="post">
                    @csrf
                    @method('put')
                    <input type="hidden" name="knowledge_base_enabled" value="0">
                    <details class="admin-ticket-message-details" @if ($errors->hasAny(['knowledge_base_enabled', 'knowledge_base_message'])) open @endif>
                        <summary class="admin-ticket-submission-summary">
                            <span class="admin-setting-icon" aria-hidden="true">▤</span>
                            <span class="admin-ticket-submission-copy">
                                <strong>Knowledge base</strong>
                                <small data-public-feature-description>{{ $knowledgeBaseEnabled ? 'Published self-service articles are available to visitors.' : 'Public knowledge-base access is paused.' }}</small>
                            </span>
                            <label class="admin-ticket-submission-switch" for="knowledge-base-enabled">
                                <span class="admin-switch-control">
                                    <input id="knowledge-base-enabled" name="knowledge_base_enabled" type="checkbox" value="1" role="switch" aria-label="Enable knowledge base" data-public-feature-toggle data-disable-title="Disable the knowledge base?" data-enabled-description="Published self-service articles are available to visitors." data-disabled-description="Public knowledge-base access is paused." data-disable-confirmation="Visitors will no longer be able to browse public articles." @checked((string) old('knowledge_base_enabled', $knowledgeBaseEnabled ? '1' : '0') === '1')>
                                    <span class="admin-switch-track" aria-hidden="true"></span>
                                </span>
                            </label>
                            <span class="admin-ticket-submission-switch-state {{ $knowledgeBaseEnabled ? '' : 'admin-access-state--inactive' }}" data-public-feature-state>{{ $knowledgeBaseEnabled ? 'Enabled' : 'Disabled' }}</span>
                            <span class="admin-ticket-message-chevron-button" aria-hidden="true">
                                <span class="admin-ticket-message-chevron"></span>
                            </span>
                        </summary>
                        <div class="admin-ticket-message-content">
                            <label class="admin-staff-field" for="knowledge-base-message">
                                <span>Message shown while the knowledge base is unavailable</span>
                                <textarea id="knowledge-base-message" name="knowledge_base_message" rows="3" maxlength="500" required>{{ old('knowledge_base_message', $knowledgeBaseMessage) }}</textarea>
                            </label>
                            <button class="admin-console-button" type="submit">Save knowledge base availability</button>
                        </div>
                    </details>
                </form>
                <form class="admin-staff-create-form admin-ticket-submission-form" action="{{ route('admin.system-settings.ticket-replies.update') }}" method="post">
                    @csrf
                    @method('put')
                    <input type="hidden" name="ticket_replies_enabled" value="0">
                    <details class="admin-ticket-message-details" @if ($errors->hasAny(['ticket_replies_enabled', 'ticket_replies_message'])) open @endif>
                        <summary class="admin-ticket-submission-summary">
                            <span class="admin-setting-icon" aria-hidden="true">↩</span>
                            <span class="admin-ticket-submission-copy">
                                <strong>Ticket replies</strong>
                                <small data-public-feature-description>{{ $ticketRepliesEnabled ? 'Requesters can continue their verified ticket conversation.' : 'Requester replies are temporarily paused.' }}</small>
                            </span>
                            <label class="admin-ticket-submission-switch" for="ticket-replies-enabled">
                                <span class="admin-switch-control">
                                    <input id="ticket-replies-enabled" name="ticket_replies_enabled" type="checkbox" value="1" role="switch" aria-label="Enable ticket replies" data-public-feature-toggle data-disable-title="Disable ticket replies?" data-enabled-description="Requesters can continue their verified ticket conversation." data-disabled-description="Requester replies are temporarily paused." data-disable-confirmation="Requesters will not be able to respond to existing tickets." @checked((string) old('ticket_replies_enabled', $ticketRepliesEnabled ? '1' : '0') === '1')>
                                    <span class="admin-switch-track" aria-hidden="true"></span>
                                </span>
                            </label>
                            <span class="admin-ticket-submission-switch-state {{ $ticketRepliesEnabled ? '' : 'admin-access-state--inactive' }}" data-public-feature-state>{{ $ticketRepliesEnabled ? 'Enabled' : 'Disabled' }}</span>
                            <span class="admin-ticket-message-chevron-button" aria-hidden="true">
                                <span class="admin-ticket-message-chevron"></span>
                            </span>
                        </summary>
                        <div class="admin-ticket-message-content">
                            <label class="admin-staff-field" for="ticket-replies-message">
                                <span>Message shown while ticket replies are unavailable</span>
                                <textarea id="ticket-replies-message" name="ticket_replies_message" rows="3" maxlength="500" required>{{ old('ticket_replies_message', $ticketRepliesMessage) }}</textarea>
                            </label>
                            <button class="admin-console-button" type="submit">Save ticket replies availability</button>
                        </div>
                    </details>
                </form>
            </section>
        </div>

        <aside class="admin-settings-aside">
            <section class="admin-console-card admin-identity-card">
                <header class="admin-console-card-heading">
                    <div><span class="admin-page-eyebrow">ORGANIZATION</span><h2>Helpdesk identity</h2><p>These details identify the service to PCU students and employees.</p></div>
                    <span class="admin-readonly-badge">Read only</span>
                </header>
                <dl class="admin-config-list">
                    <div><dt>System name</dt><dd>Ticketech</dd></div>
                    <div><dt>Service description</dt><dd>EdTech Helpdesk Support and Ticketing System</dd></div>
                    <div><dt>Support team</dt><dd>PCU IT Support</dd></div>
                    <div><dt>Time zone</dt><dd>Asia/Manila (UTC+08:00)</dd></div>
                </dl>
            </section>
            <section class="admin-console-card admin-security-card">
                <span class="admin-page-eyebrow">ACCESS CONTROL</span>
                <h2>Role boundaries</h2>
                <div class="admin-role-summary"><span class="admin-role-icon admin-role-icon--admin" aria-hidden="true">♙</span><div><strong>Administrator</strong><p>Moderation, audit logs, and console access.</p></div></div>
                <div class="admin-role-summary"><span class="admin-role-icon" aria-hidden="true">♙</span><div><strong>Helpdesk staff</strong><p>Ticket handling, replies, and client reporting.</p></div></div>
                <p class="admin-security-footnote">SMTP passwords and Turnstile secret keys are stored encrypted. Secret values are write-only and never included in settings pages.</p>
            </section>
        </aside>
    </div>
</div>
@endsection
