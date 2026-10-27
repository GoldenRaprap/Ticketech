<?php

namespace App\Http\Controllers;

use App\Services\AuditActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class AdminSystemSettingsController extends Controller
{
    public function index(): View
    {
        $settings = DB::table('system_settings')
            ->whereIn('setting', [
                'mail.from.address',
                'mail.from.name',
                'mail.smtp.host',
                'mail.smtp.port',
                'mail.smtp.encryption',
                'mail.smtp.username',
                'mail.smtp.password',
            ])
            ->pluck('value', 'setting');
        $mailer = config('mail.default');
        $smtpPort = $settings->get('mail.smtp.port', config('mail.mailers.smtp.port'));
        $smtpScheme = config('mail.mailers.smtp.scheme');
        $smtpEncryption = $settings->get(
            'mail.smtp.encryption',
            $smtpScheme === 'smtps' || (int) $smtpPort === 465 ? 'ssl' : 'tls',
        );
        $mailTransportStatus = match ($mailer) {
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
            'mailTransportStatus' => $mailTransportStatus,
        ]);
    }

    public function update(Request $request, AuditActivityLogger $auditLogger): RedirectResponse
    {
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

        $scheme = match ($validated['smtp_encryption']) {
            'ssl' => 'smtps',
            default => 'smtp',
        };

        config([
            'mail.from.address' => $validated['from_address'],
            'mail.from.name' => $validated['from_name'],
            'mail.mailers.smtp.host' => $validated['smtp_host'],
            'mail.mailers.smtp.port' => (int) $validated['smtp_port'],
            'mail.mailers.smtp.scheme' => $scheme,
            'mail.mailers.smtp.username' => $validated['smtp_username'],
            'mail.mailers.smtp.password' => filled($validated['smtp_app_password'] ?? null)
                ? $validated['smtp_app_password']
                : ($request->boolean('clear_smtp_app_password')
                    ? null
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
}
