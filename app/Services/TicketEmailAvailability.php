<?php

namespace App\Services;

use App\Exceptions\SystemMailSettingsUnavailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class TicketEmailAvailability
{
    public const DELIVERY_STATUS_SETTING = 'mail.delivery.status';

    public const DELIVERY_MESSAGE_SETTING = 'mail.delivery.message';

    public const DELIVERY_UNAVAILABLE_STATUS = 'attention';

    public const SUBMISSION_UNAVAILABLE_MESSAGE = 'Ticket submissions are temporarily unavailable because email verification cannot currently be sent. Please try again later.';

    public const REPLIES_UNAVAILABLE_MESSAGE = 'Ticket replies are temporarily unavailable because email notifications cannot currently be sent. Please try again later.';

    public const GENERIC_DELIVERY_FAILURE_MESSAGE = 'Automatic email delivery failed. Check the SMTP credentials and mail server connection. Ticket submissions and replies were paused. After confirming email delivery works, re-enable the ticket features in Admin System Settings.';

    public function disableTicketEmailFeatures(Throwable $exception): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        $wasAlreadyUnavailable = DB::transaction(function () use ($exception): bool {
            $settings = DB::table('system_settings')
                ->whereIn('setting', [
                    self::DELIVERY_STATUS_SETTING,
                    self::DELIVERY_MESSAGE_SETTING,
                    TicketSubmissionSettings::ENABLED_SETTING,
                    TicketSubmissionSettings::MESSAGE_SETTING,
                    PublicFeatureAvailability::TICKET_REPLIES_ENABLED,
                    PublicFeatureAvailability::TICKET_REPLIES_MESSAGE,
                ])
                ->pluck('value', 'setting');

            $wasAlreadyUnavailable = $settings->get(self::DELIVERY_STATUS_SETTING) === self::DELIVERY_UNAVAILABLE_STATUS;
            $failureMessage = $exception instanceof SystemMailSettingsUnavailable
                ? $exception->getMessage()
                : self::GENERIC_DELIVERY_FAILURE_MESSAGE;

            $this->updateSetting(
                TicketSubmissionSettings::ENABLED_SETTING,
                '0',
                $settings->get(TicketSubmissionSettings::ENABLED_SETTING),
                $settings->has(TicketSubmissionSettings::ENABLED_SETTING),
            );
            $this->updateSetting(
                PublicFeatureAvailability::TICKET_REPLIES_ENABLED,
                '0',
                $settings->get(PublicFeatureAvailability::TICKET_REPLIES_ENABLED),
                $settings->has(PublicFeatureAvailability::TICKET_REPLIES_ENABLED),
            );

            if ($settings->get(TicketSubmissionSettings::ENABLED_SETTING, '1') !== '0') {
                $this->updateSetting(
                    TicketSubmissionSettings::MESSAGE_SETTING,
                    self::SUBMISSION_UNAVAILABLE_MESSAGE,
                    $settings->get(TicketSubmissionSettings::MESSAGE_SETTING),
                    $settings->has(TicketSubmissionSettings::MESSAGE_SETTING),
                );
            }

            if ($settings->get(PublicFeatureAvailability::TICKET_REPLIES_ENABLED, '1') !== '0') {
                $this->updateSetting(
                    PublicFeatureAvailability::TICKET_REPLIES_MESSAGE,
                    self::REPLIES_UNAVAILABLE_MESSAGE,
                    $settings->get(PublicFeatureAvailability::TICKET_REPLIES_MESSAGE),
                    $settings->has(PublicFeatureAvailability::TICKET_REPLIES_MESSAGE),
                );
            }

            $this->updateSetting(
                self::DELIVERY_STATUS_SETTING,
                self::DELIVERY_UNAVAILABLE_STATUS,
                $settings->get(self::DELIVERY_STATUS_SETTING),
                $settings->has(self::DELIVERY_STATUS_SETTING),
            );
            $this->updateSetting(
                self::DELIVERY_MESSAGE_SETTING,
                $failureMessage,
                $settings->get(self::DELIVERY_MESSAGE_SETTING),
                $settings->has(self::DELIVERY_MESSAGE_SETTING),
            );

            return $wasAlreadyUnavailable;
        });

        if (! $wasAlreadyUnavailable) {
            Log::critical('Automatic email delivery failed; public ticket submissions and replies were paused.', [
                'exception' => $exception::class,
            ]);
        }
    }

    public function clearDeliveryFailureAfterCredentialReplacement(): void
    {
        DB::table('system_settings')
            ->whereIn('setting', [
                self::DELIVERY_STATUS_SETTING,
                self::DELIVERY_MESSAGE_SETTING,
            ])
            ->delete();
    }

    private function updateSetting(string $setting, string $value, ?string $currentValue, bool $exists): void
    {
        if ($exists && $currentValue === $value) {
            return;
        }

        DB::table('system_settings')->updateOrInsert(
            ['setting' => $setting],
            ['value' => $value, 'created_at' => now(), 'updated_at' => now()],
        );
    }
}
