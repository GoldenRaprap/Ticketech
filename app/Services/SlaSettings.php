<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class SlaSettings
{
    private const BUSINESS_HOURS_OPENING_SETTING = 'sla.business_hours.opening_time';

    private const BUSINESS_HOURS_CLOSING_SETTING = 'sla.business_hours.closing_time';

    private const DEFAULT_BUSINESS_HOURS = [
        'opening_time' => '09:00',
        'closing_time' => '17:00',
    ];

    /**
     * @var array<string, array{
     *     priority: string,
     *     class: string,
     *     description: string,
     *     first_response: array{value: int, unit: string},
     *     resolution: array{value: int, unit: string}
     * }>
     */
    private const DEFAULT_TARGETS = [
        'urgent' => [
            'priority' => 'Urgent',
            'class' => 'admin-sla-card--urgent',
            'description' => 'Immediate attention',
            'first_response' => ['value' => 1, 'unit' => 'business_hour'],
            'resolution' => ['value' => 4, 'unit' => 'business_hour'],
        ],
        'high' => [
            'priority' => 'High',
            'class' => 'admin-sla-card--high',
            'description' => 'Expedited response',
            'first_response' => ['value' => 4, 'unit' => 'business_hour'],
            'resolution' => ['value' => 1, 'unit' => 'business_day'],
        ],
        'medium' => [
            'priority' => 'Normal',
            'class' => 'admin-sla-card--medium',
            'description' => 'Standard response',
            'first_response' => ['value' => 1, 'unit' => 'business_day'],
            'resolution' => ['value' => 3, 'unit' => 'business_day'],
        ],
        'low' => [
            'priority' => 'Low',
            'class' => 'admin-sla-card--low',
            'description' => 'General queue',
            'first_response' => ['value' => 2, 'unit' => 'business_day'],
            'resolution' => ['value' => 5, 'unit' => 'business_day'],
        ],
    ];

    /**
     * @return array<string, array{
     *     priority: string,
     *     class: string,
     *     description: string,
     *     first_response: array{value: int, unit: string},
     *     resolution: array{value: int, unit: string}
     * }>
     */
    public function targets(): array
    {
        $settingKeys = [];

        foreach (array_keys(self::DEFAULT_TARGETS) as $priority) {
            foreach (['first_response', 'resolution'] as $target) {
                foreach (['value', 'unit'] as $property) {
                    $settingKeys[] = "sla.{$priority}.{$target}.{$property}";
                }
            }
        }

        $storedSettings = DB::table('system_settings')
            ->whereIn('setting', $settingKeys)
            ->pluck('value', 'setting');
        $targets = self::DEFAULT_TARGETS;

        foreach ($targets as $priority => &$targetSettings) {
            foreach (['first_response', 'resolution'] as $target) {
                $valueKey = "sla.{$priority}.{$target}.value";
                $unitKey = "sla.{$priority}.{$target}.unit";

                if ($storedSettings->has($valueKey)) {
                    $targetSettings[$target]['value'] = (int) $storedSettings->get($valueKey);
                }

                if ($storedSettings->has($unitKey)) {
                    $targetSettings[$target]['unit'] = $storedSettings->get($unitKey);
                }
            }
        }

        return $targets;
    }

    /**
     * @return array{opening_time: string, closing_time: string, minutes_per_day: int}
     */
    public function businessHours(): array
    {
        $storedSettings = DB::table('system_settings')
            ->whereIn('setting', [
                self::BUSINESS_HOURS_OPENING_SETTING,
                self::BUSINESS_HOURS_CLOSING_SETTING,
            ])
            ->pluck('value', 'setting');
        $openingTime = $storedSettings->get(
            self::BUSINESS_HOURS_OPENING_SETTING,
            self::DEFAULT_BUSINESS_HOURS['opening_time'],
        );
        $closingTime = $storedSettings->get(
            self::BUSINESS_HOURS_CLOSING_SETTING,
            self::DEFAULT_BUSINESS_HOURS['closing_time'],
        );
        $minutesPerDay = $this->minutesAfterMidnight($closingTime)
            - $this->minutesAfterMidnight($openingTime);

        if ($minutesPerDay <= 0) {
            throw new UnexpectedValueException('Configured business closing time must be later than its opening time.');
        }

        return [
            'opening_time' => $openingTime,
            'closing_time' => $closingTime,
            'minutes_per_day' => $minutesPerDay,
        ];
    }

    /**
     * @param  array{opening_time: string, closing_time: string}  $businessHours
     */
    public function updateBusinessHours(array $businessHours): void
    {
        $minutesPerDay = $this->minutesAfterMidnight($businessHours['closing_time'])
            - $this->minutesAfterMidnight($businessHours['opening_time']);

        if ($minutesPerDay <= 0) {
            throw new UnexpectedValueException('Configured business closing time must be later than its opening time.');
        }

        DB::transaction(function () use ($businessHours): void {
            foreach ([
                self::BUSINESS_HOURS_OPENING_SETTING => $businessHours['opening_time'],
                self::BUSINESS_HOURS_CLOSING_SETTING => $businessHours['closing_time'],
            ] as $setting => $value) {
                DB::table('system_settings')->updateOrInsert(
                    ['setting' => $setting],
                    ['value' => $value, 'created_at' => now(), 'updated_at' => now()],
                );
            }
        });
    }

    /**
     * @param  array<string, array{
     *     first_response: array{value: int, unit: string},
     *     resolution: array{value: int, unit: string}
     * }>  $targets
     */
    public function update(array $targets): void
    {
        DB::transaction(function () use ($targets): void {
            foreach ($targets as $priority => $priorityTargets) {
                foreach (['first_response', 'resolution'] as $target) {
                    foreach ($priorityTargets[$target] as $property => $value) {
                        DB::table('system_settings')->updateOrInsert(
                            ['setting' => "sla.{$priority}.{$target}.{$property}"],
                            ['value' => (string) $value, 'created_at' => now(), 'updated_at' => now()],
                        );
                    }
                }
            }
        });
    }

    private function minutesAfterMidnight(string $time): int
    {
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $time) !== 1) {
            throw new UnexpectedValueException('Configured business hours must use a valid 24-hour time.');
        }

        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return ($hours * 60) + $minutes;
    }
}
