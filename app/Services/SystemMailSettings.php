<?php

namespace App\Services;

use App\Exceptions\SystemMailSettingsUnavailable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\MissingAppKeyException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class SystemMailSettings
{
    public function apply(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

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
            ])
            ->pluck('value', 'setting');
        $encryption = $settings->get('mail.smtp.encryption');
        $smtpPassword = config('mail.smtp_password_fallback');

        if ($settings->has('mail.smtp.password')) {
            try {
                $smtpPassword = Crypt::decryptString($settings->get('mail.smtp.password'));
            } catch (DecryptException|MissingAppKeyException $exception) {
                throw new SystemMailSettingsUnavailable(
                    SystemMailSettingsUnavailable::KEY_UNAVAILABLE_MESSAGE,
                    $exception,
                );
            }
        }

        $mailConfig = [
            'mail.from.address' => $settings->get('mail.from.address', config('mail.from.address')),
            'mail.from.name' => $settings->get('mail.from.name', config('mail.from.name')),
            'mail.mailers.smtp.url' => filled(config('mail.mailers.smtp.url'))
                ? config('mail.mailers.smtp.url')
                : null,
            'mail.mailers.smtp.host' => $settings->get('mail.smtp.host', config('mail.mailers.smtp.host')),
            'mail.mailers.smtp.port' => (int) $settings->get('mail.smtp.port', config('mail.mailers.smtp.port')),
            'mail.mailers.smtp.scheme' => match ($encryption) {
                'ssl' => 'smtps',
                'tls', 'none' => 'smtp',
                default => config('mail.mailers.smtp.scheme'),
            },
            'mail.mailers.smtp.username' => $settings->get('mail.smtp.username', config('mail.mailers.smtp.username')),
            'mail.mailers.smtp.password' => $smtpPassword,
            'mail.mailers.smtp.auto_tls' => $encryption === null
                ? config('mail.mailers.smtp.auto_tls', true)
                : $encryption !== 'none',
        ];

        $storedDefaultMailer = $settings->get('mail.default');
        $smtpIsConfigured = filled($mailConfig['mail.mailers.smtp.host'])
            && filled($mailConfig['mail.mailers.smtp.username'])
            && filled($smtpPassword);
        $fallbackMailer = config('mail.default_fallback');
        $configuredDefaultMailer = config('mail.default');

        if (! filled($storedDefaultMailer) || ! isset(config('mail.mailers')[$storedDefaultMailer])) {
            if ($smtpIsConfigured) {
                $storedDefaultMailer = 'smtp';
            } elseif (
                ! $settings->has('mail.default')
                && filled($configuredDefaultMailer)
                && isset(config('mail.mailers')[$configuredDefaultMailer])
            ) {
                $storedDefaultMailer = $configuredDefaultMailer;
            } else {
                $storedDefaultMailer = filled($fallbackMailer) && isset(config('mail.mailers')[$fallbackMailer])
                    ? $fallbackMailer
                    : (filled($configuredDefaultMailer) && isset(config('mail.mailers')[$configuredDefaultMailer])
                        ? $configuredDefaultMailer
                        : 'log');
            }
        }

        $mailConfig['mail.default'] = $storedDefaultMailer;

        config($mailConfig);

        Mail::purge('smtp');

        if (! app()->environment('testing')) {
            $defaultMailer = config('mail.default');

            if (in_array($defaultMailer, ['log', 'array'], true)) {
                throw new SystemMailSettingsUnavailable(SystemMailSettingsUnavailable::EMAIL_NOT_CONFIGURED_MESSAGE);
            }

            if (
                $defaultMailer === 'smtp'
                && (! filled(config('mail.mailers.smtp.host'))
                    || ! filled(config('mail.mailers.smtp.username'))
                    || ! filled(config('mail.mailers.smtp.password')))
            ) {
                throw new SystemMailSettingsUnavailable(SystemMailSettingsUnavailable::EMAIL_NOT_CONFIGURED_MESSAGE);
            }
        }
    }
}
