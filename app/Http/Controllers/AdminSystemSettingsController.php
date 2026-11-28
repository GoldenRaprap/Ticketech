<?php

namespace App\Http\Controllers;

use App\Services\AuditActivityLogger;
use App\Services\PublicFeatureAvailability;
use App\Services\SlaSettings;
use App\Services\TicketEmailAvailability;
use App\Services\TicketResolutionSla;
use App\Services\TicketSubmissionSettings;
use App\Services\TurnstileVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class AdminSystemSettingsController extends Controller
{
    public function index(SlaSettings $slaSettings, TurnstileVerifier $turnstileVerifier): View
    {
        $settings = DB::table('system_settings')
            ->whereIn('setting', [
                'mail.default',
                'mail.from.address',
                'mail.from.name',
                'mail.smtp.host',
                'mail.smtp.port',
                'mail.smtp.encryption',
                'mail.smtp.username',
                'mail.smtp.password',
                TurnstileVerifier::SITE_KEY_SETTING,
                TurnstileVerifier::SECRET_KEY_SETTING,
                'ticket.submission.enabled',
                'ticket.submission.message',
                PublicFeatureAvailability::KNOWLEDGE_BASE_ENABLED,
                PublicFeatureAvailability::KNOWLEDGE_BASE_MESSAGE,
                PublicFeatureAvailability::TICKET_REPLIES_ENABLED,
                PublicFeatureAvailability::TICKET_REPLIES_MESSAGE,
                TicketEmailAvailability::DELIVERY_STATUS_SETTING,
                TicketEmailAvailability::DELIVERY_MESSAGE_SETTING,
            ])
            ->pluck('value', 'setting');
        $mailer = config('mail.default');
        $smtpPort = $settings->get('mail.smtp.port', config('mail.mailers.smtp.port'));
        $smtpScheme = config('mail.mailers.smtp.scheme');
        $smtpEncryption = $settings->get(
            'mail.smtp.encryption',
            $smtpScheme === 'smtps' || (int) $smtpPort === 465 ? 'ssl' : 'tls',
        );
        $mailTransportStatus = $settings->get(TicketEmailAvailability::DELIVERY_STATUS_SETTING)
            === TicketEmailAvailability::DELIVERY_UNAVAILABLE_STATUS
                ? 'Needs attention'
                : match ($mailer) {
                    'log', 'array' => 'Not sending',
                    'smtp' => filled($settings->get('mail.smtp.host', config('mail.mailers.smtp.host')))
                        && filled($settings->get('mail.smtp.username', config('mail.mailers.smtp.username')))
                        && (filled($settings->get('mail.smtp.password')) || filled(config('mail.mailers.smtp.password')))
                        ? 'Configured'
                        : 'Credentials needed',
                    default => 'Configured',
                };

        return view('staff.system-settings', [
            'fromAddress' => $settings->get('mail.from.address', config('mail.from.address')),
            'fromName' => $settings->get('mail.from.name', config('mail.from.name')),
            'smtpHost' => $settings->get('mail.smtp.host', config('mail.mailers.smtp.host')),
            'smtpPort' => $smtpPort,
            'smtpEncryption' => $smtpEncryption,
            'smtpUsername' => $settings->get('mail.smtp.username', config('mail.mailers.smtp.username')),
            'hasSmtpAppPassword' => $settings->has('mail.smtp.password')
                || filled(config('mail.mailers.smtp.password')),
            'turnstileSiteKey' => $turnstileVerifier->siteKey(),
            'turnstileConfigured' => $turnstileVerifier->isConfigured(),
            'hasTurnstileSiteKey' => $settings->has(TurnstileVerifier::SITE_KEY_SETTING)
                || filled(config('services.turnstile.site_key')),
            'hasTurnstileSecret' => $settings->has(TurnstileVerifier::SECRET_KEY_SETTING)
                || filled(config('services.turnstile.secret_key')),
            'hasSavedTurnstileSiteKey' => $settings->has(TurnstileVerifier::SITE_KEY_SETTING),
            'hasSavedTurnstileSecret' => $settings->has(TurnstileVerifier::SECRET_KEY_SETTING),
            'mailTransportStatus' => $mailTransportStatus,
            'mailDeliveryFailureMessage' => $settings->get(TicketEmailAvailability::DELIVERY_MESSAGE_SETTING),
            'ticketSubmissionOpen' => $settings->get('ticket.submission.enabled', '1') === '1',
            'ticketSubmissionMessage' => $settings->get(
                'ticket.submission.message',
                TicketSubmissionSettings::DEFAULT_CLOSED_MESSAGE,
            ),
            'knowledgeBaseEnabled' => $settings->get(PublicFeatureAvailability::KNOWLEDGE_BASE_ENABLED, '1') === '1',
            'knowledgeBaseMessage' => $settings->get(
                PublicFeatureAvailability::KNOWLEDGE_BASE_MESSAGE,
                PublicFeatureAvailability::DEFAULT_KNOWLEDGE_BASE_MESSAGE,
            ),
            'ticketRepliesEnabled' => $settings->get(PublicFeatureAvailability::TICKET_REPLIES_ENABLED, '1') === '1',
            'ticketRepliesMessage' => $settings->get(
                PublicFeatureAvailability::TICKET_REPLIES_MESSAGE,
                PublicFeatureAvailability::DEFAULT_TICKET_REPLIES_MESSAGE,
            ),
            'businessHours' => $slaSettings->businessHours(),
        ]);
    }

    public function updateBusinessHours(
        Request $request,
        SlaSettings $slaSettings,
        TicketResolutionSla $resolutionSla,
        AuditActivityLogger $auditLogger,
    ): RedirectResponse {
        $validator = Validator::make($request->all(), [
            'opening_time' => ['required', 'date_format:H:i'],
            'closing_time' => ['required', 'date_format:H:i'],
        ]);
        $validator->after(function (\Illuminate\Validation\Validator $validator) use ($request): void {
            if ($validator->errors()->hasAny(['opening_time', 'closing_time'])) {
                return;
            }

            if ($request->input('closing_time') <= $request->input('opening_time')) {
                $validator->errors()->add('closing_time', 'The closing time must be later than the opening time.');
            }
        });
        $validated = $validator->validate();
        $previousBusinessHours = $slaSettings->businessHours();
        $updatedBusinessHours = [
            'opening_time' => $validated['opening_time'],
            'closing_time' => $validated['closing_time'],
        ];

        $slaSettings->updateBusinessHours($updatedBusinessHours);
        $resolutionSla->refreshOpenTickets();

        $auditLogger->record(
            $request,
            'business_hours_updated',
            'Updated the business hours used for resolution targets.',
            properties: [
                'previous_business_hours' => [
                    'opening_time' => $previousBusinessHours['opening_time'],
                    'closing_time' => $previousBusinessHours['closing_time'],
                ],
                'business_hours' => $updatedBusinessHours,
            ],
        );

        return to_route('admin.system-settings')->with('status', 'Business hours updated.');
    }

    public function updateKnowledgeBaseAvailability(Request $request, AuditActivityLogger $auditLogger): RedirectResponse
    {
        return $this->updatePublicFeatureAvailability(
            $request,
            $auditLogger,
            'knowledge_base_enabled',
            'knowledge_base_message',
            PublicFeatureAvailability::KNOWLEDGE_BASE_ENABLED,
            PublicFeatureAvailability::KNOWLEDGE_BASE_MESSAGE,
            'knowledge_base_availability_updated',
            'Knowledge base',
        );
    }

    public function updateTicketRepliesAvailability(Request $request, AuditActivityLogger $auditLogger): RedirectResponse
    {
        return $this->updatePublicFeatureAvailability(
            $request,
            $auditLogger,
            'ticket_replies_enabled',
            'ticket_replies_message',
            PublicFeatureAvailability::TICKET_REPLIES_ENABLED,
            PublicFeatureAvailability::TICKET_REPLIES_MESSAGE,
            'ticket_replies_availability_updated',
            'Ticket replies',
        );
    }

    public function updateTicketSubmission(Request $request, AuditActivityLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'submission_enabled' => ['required', 'boolean'],
            'submission_message' => ['required', 'string', 'max:500'],
        ]);

        $isOpen = (bool) $validated['submission_enabled'];
        $settings = [
            'ticket.submission.enabled' => $isOpen ? '1' : '0',
            'ticket.submission.message' => $validated['submission_message'],
        ];

        DB::transaction(function () use ($settings): void {
            foreach ($settings as $setting => $value) {
                DB::table('system_settings')->updateOrInsert(
                    ['setting' => $setting],
                    ['value' => $value, 'created_at' => now(), 'updated_at' => now()],
                );
            }
        });

        $auditLogger->record(
            $request,
            'ticket_submission_settings_updated',
            $isOpen ? 'Reopened public ticket submission.' : 'Paused public ticket submission.',
            properties: ['submission_enabled' => $isOpen],
        );

        return to_route('admin.system-settings')->with(
            'status',
            $isOpen ? 'Public ticket submission is open.' : 'Public ticket submission is paused.',
        );
    }

    private function updatePublicFeatureAvailability(
        Request $request,
        AuditActivityLogger $auditLogger,
        string $enabledField,
        string $messageField,
        string $enabledSetting,
        string $messageSetting,
        string $auditEvent,
        string $featureName,
    ): RedirectResponse {
        $validated = $request->validate([
            $enabledField => ['required', 'boolean'],
            $messageField => ['required', 'string', 'max:500'],
        ]);
        $isEnabled = (bool) $validated[$enabledField];
        $settings = [
            $enabledSetting => $isEnabled ? '1' : '0',
            $messageSetting => $validated[$messageField],
        ];

        DB::transaction(function () use ($settings): void {
            foreach ($settings as $setting => $value) {
                DB::table('system_settings')->updateOrInsert(
                    ['setting' => $setting],
                    ['value' => $value, 'created_at' => now(), 'updated_at' => now()],
                );
            }
        });

        $auditLogger->record(
            $request,
            $auditEvent,
            ($isEnabled ? 'Enabled ' : 'Disabled ').'public '.$featureName.'.',
            properties: [$enabledField => $isEnabled],
        );

        return to_route('admin.system-settings')->with(
            'status',
            $featureName.($isEnabled ? ' is enabled.' : ' is disabled.'),
        );
    }

    public function update(
        Request $request,
        AuditActivityLogger $auditLogger,
        TicketEmailAvailability $ticketEmailAvailability,
    ): RedirectResponse {
        $validator = Validator::make($request->all(), [
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'smtp_encryption' => ['required', 'in:tls,ssl,none'],
            'smtp_username' => ['required', 'string', 'max:255'],
            'smtp_app_password' => ['nullable', 'string', 'max:512'],
            'clear_smtp_app_password' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput($request->except('smtp_app_password'));
        }

        $validated = $validator->validated();
        $settings = [
            'mail.default' => $this->hasSmtpPassword($validated, $request)
                ? 'smtp'
                : (config('mail.default_fallback') ?: 'log'),
            'mail.from.address' => $validated['from_address'],
            'mail.from.name' => $validated['from_name'],
            'mail.smtp.host' => $validated['smtp_host'],
            'mail.smtp.port' => (string) $validated['smtp_port'],
            'mail.smtp.encryption' => $validated['smtp_encryption'],
            'mail.smtp.username' => $validated['smtp_username'],
        ];

        DB::transaction(function () use ($validated, $request, $settings): void {
            if (filled($validated['smtp_app_password'] ?? null)) {
                $settings['mail.smtp.password'] = Crypt::encryptString($validated['smtp_app_password']);
            } elseif ($request->boolean('clear_smtp_app_password')) {
                DB::table('system_settings')->where('setting', 'mail.smtp.password')->delete();
            }

            foreach ($settings as $setting => $value) {
                DB::table('system_settings')->updateOrInsert(
                    ['setting' => $setting],
                    ['value' => $value, 'created_at' => now(), 'updated_at' => now()],
                );
            }
        });

        $ticketEmailAvailability->clearDeliveryFailureAfterSettingsUpdate();

        $scheme = match ($validated['smtp_encryption']) {
            'ssl' => 'smtps',
            default => 'smtp',
        };

        config([
            'mail.default' => $settings['mail.default'],
            'mail.from.address' => $validated['from_address'],
            'mail.from.name' => $validated['from_name'],
            'mail.mailers.smtp.host' => $validated['smtp_host'],
            'mail.mailers.smtp.port' => (int) $validated['smtp_port'],
            'mail.mailers.smtp.scheme' => $scheme,
            'mail.mailers.smtp.username' => $validated['smtp_username'],
            'mail.mailers.smtp.password' => filled($validated['smtp_app_password'] ?? null)
                ? $validated['smtp_app_password']
                : ($request->boolean('clear_smtp_app_password')
                    ? config('mail.smtp_password_fallback')
                    : config('mail.mailers.smtp.password')),
            'mail.mailers.smtp.auto_tls' => $validated['smtp_encryption'] !== 'none',
        ]);
        Mail::purge('smtp');
        $changedSettings = array_keys($settings);

        if (filled($validated['smtp_app_password'] ?? null) || $request->boolean('clear_smtp_app_password')) {
            $changedSettings[] = 'mail.smtp.password';
        }

        $auditLogger->record(
            $request,
            'system_settings_updated',
            'Updated system email delivery settings.',
            properties: ['updated_settings' => $changedSettings],
        );

        return to_route('admin.system-settings')->with('status', 'Email sender and SMTP settings updated.');
    }

    public function updateTurnstileSettings(Request $request, AuditActivityLogger $auditLogger): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'turnstile_site_key' => ['nullable', 'string', 'max:255'],
            'turnstile_secret_key' => ['nullable', 'string', 'max:512'],
            'clear_turnstile_site_key' => ['nullable', 'boolean'],
            'clear_turnstile_secret_key' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput($request->except('turnstile_secret_key'));
        }

        $validated = $validator->validated();
        $settings = [];
        $settingsToClear = [];

        if ($request->boolean('clear_turnstile_site_key')) {
            $settingsToClear[] = TurnstileVerifier::SITE_KEY_SETTING;
        } elseif (filled($validated['turnstile_site_key'] ?? null)) {
            $settings[TurnstileVerifier::SITE_KEY_SETTING] = $validated['turnstile_site_key'];
        }

        if ($request->boolean('clear_turnstile_secret_key')) {
            $settingsToClear[] = TurnstileVerifier::SECRET_KEY_SETTING;
        } elseif (filled($validated['turnstile_secret_key'] ?? null)) {
            $settings[TurnstileVerifier::SECRET_KEY_SETTING] = Crypt::encryptString(
                $validated['turnstile_secret_key'],
            );
        }

        if ($settings === [] && $settingsToClear === []) {
            return to_route('admin.system-settings')->with('status', 'No Turnstile settings were changed.');
        }

        DB::transaction(function () use ($settings, $settingsToClear): void {
            foreach ($settingsToClear as $setting) {
                DB::table('system_settings')->where('setting', $setting)->delete();
            }

            foreach ($settings as $setting => $value) {
                DB::table('system_settings')->updateOrInsert(
                    ['setting' => $setting],
                    ['value' => $value, 'created_at' => now(), 'updated_at' => now()],
                );
            }
        });

        $auditLogger->record(
            $request,
            'turnstile_settings_updated',
            'Updated Cloudflare Turnstile settings.',
            properties: [
                'updated_settings' => array_keys($settings),
                'cleared_settings' => $settingsToClear,
            ],
        );

        return to_route('admin.system-settings')->with('status', 'Turnstile settings updated.');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function hasSmtpPassword(array $validated, Request $request): bool
    {
        return filled($validated['smtp_app_password'] ?? null)
            || (! $request->boolean('clear_smtp_app_password')
                && DB::table('system_settings')->where('setting', 'mail.smtp.password')->exists())
            || filled(config('mail.smtp_password_fallback'));
    }
}
