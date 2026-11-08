<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
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
     * Run the migrations.
     */
    public function up(): void
    {
        $timestamp = now();

        foreach (self::DEFAULT_KEYWORDS as $priority => $keywords) {
            DB::table('system_settings')->insertOrIgnore([
                'setting' => "ticket_priority.{$priority}.keywords",
                'value' => $keywords,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::DEFAULT_KEYWORDS as $priority => $keywords) {
            DB::table('system_settings')
                ->where('setting', "ticket_priority.{$priority}.keywords")
                ->where('value', $keywords)
                ->delete();
        }
    }
};
