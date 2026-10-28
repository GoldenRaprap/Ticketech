<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SlaSettings
{
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
            'priority' => 'Medium',
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
}
