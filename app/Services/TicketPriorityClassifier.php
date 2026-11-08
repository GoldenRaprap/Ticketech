<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TicketPriorityClassifier
{
    /**
     * @var array<string, string>
     */
    private const DEFAULT_KEYWORDS = [
        'urgent' => 'urgent, critical, emergency, outage, system-wide outage, complete downtime, total system failure, server down, system unavailable, platform inaccessible, website down, application crash, database failure, server failure, service disruption, critical system error, production failure, system not responding, unexpected shutdown, entire network down, campus-wide internet outage, network outage, main server disconnected, router failure, security breach, data breach, unauthorized access, compromised account, hacked account, ransomware attack, malware outbreak, data leakage, exposed sensitive information, university-wide disruption, enrollment system down, student portal inaccessible, learning platform down, online classes disrupted, all users affected, multiple departments affected',
        'high' => 'high, major, severe, login failure, unable to log in, cannot sign in, account locked, authentication failed, invalid credentials, access denied, password reset failure, authentication error, account inaccessible, server error, Priism error, student portal error, LMS malfunction, learning platform inaccessible, enrollment error, registration failure, grade submission failure, student records unavailable, online classroom unavailable, cannot submit requirements, cannot upload assignments, internet unavailable, Wi-Fi disconnected, cannot connect to network, DNS server error, network authentication failure, frequent disconnections, LAN connection failure, computer won\'t boot, operating system failure, blue screen error, computer keeps restarting, printer unavailable, server connection failure, workstation malfunction, application keeps crashing, critical software malfunction',
        'medium' => 'medium, moderate, intermittent connection, slow loading, slow performance, system lag, delayed response, long loading time, application freezing, occasional crashing, high latency, slow processing, unresponsive interface, delayed synchronization, unstable Wi-Fi, weak signal, slow internet, frequent lag, packet loss, limited connectivity, connection timeout, temporary disconnection, network congestion, page not loading properly, missing information, incorrect display, upload delay, download issue, partial system error, search function not working, notification delay, dashboard not updating, broken links, minor portal error, incomplete records display, keyboard malfunction, mouse not responding, monitor flickering, printer paper jam, printer connection issue, scanner malfunction, headphone malfunction, microphone not working, webcam issue, USB port not working',
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
