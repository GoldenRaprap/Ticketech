<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TicketPriorityClassifier
{
    /**
     * @var array<string, string>
     */
    private const DEFAULT_KEYWORDS = [
        'urgent' => 'urgent, critical, emergency, outage',
        'high' => 'work stopped, system unavailable',
        'medium' => 'work affected, access issue',
        'low' => '',
    ];

    /**
     * @return array<string, string>
     */
    public function keywords(): array
    {
        $settingKeys = array_map(
            fn (string $priority): string => "ticket_priority.{$priority}.keywords",
            array_keys(self::DEFAULT_KEYWORDS),
        );
        $storedSettings = DB::table('system_settings')
            ->whereIn('setting', $settingKeys)
            ->pluck('value', 'setting');

        $keywords = self::DEFAULT_KEYWORDS;

        foreach (array_keys($keywords) as $priority) {
            $settingKey = "ticket_priority.{$priority}.keywords";

            if ($storedSettings->has($settingKey)) {
                $keywords[$priority] = $storedSettings->get($settingKey);
            }
        }

        return $keywords;
    }

    public function classify(string $subject): string
    {
        $subject = mb_strtolower($subject);

        foreach ($this->keywords() as $priority => $keywordList) {
            foreach ($this->parseKeywords($keywordList) as $keyword) {
                $pattern = '/(?<![\pL\pN])'.str_replace(' ', '\s+', preg_quote($keyword, '/')).'(?![\pL\pN])/iu';

                if (preg_match($pattern, $subject) === 1) {
                    return ucfirst($priority);
                }
            }
        }

        return 'Low';
    }

    /**
     * @param  array<string, string|null>  $keywords
     */
    public function update(array $keywords): void
    {
        DB::transaction(function () use ($keywords): void {
            foreach ($keywords as $priority => $keywordList) {
                DB::table('system_settings')->updateOrInsert(
                    ['setting' => "ticket_priority.{$priority}.keywords"],
                    ['value' => $keywordList ?? '', 'created_at' => now(), 'updated_at' => now()],
                );
            }
        });
    }

    /**
     * @return list<string>
     */
    private function parseKeywords(string $keywordList): array
    {
        $keywords = preg_split('/[\r\n,]+/', mb_strtolower($keywordList), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter(array_map('trim', $keywords ?: [])));
    }
}
