<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketEmailVerification;
use App\Services\SlaSettings;
use App\Services\TicketDepartmentClassifier;
use App\Services\TicketPriorityClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminSlaSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_subject_keywords_are_preinstalled_for_a_new_database(): void
    {
        $defaultKeywords = [
            'urgent' => 'urgent, critical, emergency, outage, system-wide outage, complete downtime, total system failure, server down, system unavailable, platform inaccessible, website down, application crash, database failure, server failure, service disruption, critical system error, production failure, system not responding, unexpected shutdown, entire network down, campus-wide internet outage, network outage, main server disconnected, router failure, security breach, data breach, unauthorized access, compromised account, hacked account, ransomware attack, malware outbreak, data leakage, exposed sensitive information, university-wide disruption, enrollment system down, student portal inaccessible, learning platform down, online classes disrupted, all users affected, multiple departments affected',
            'high' => 'high, major, severe, login failure, unable to log in, cannot sign in, account locked, authentication failed, invalid credentials, access denied, password reset failure, authentication error, account inaccessible, server error, Priism error, student portal error, LMS malfunction, learning platform inaccessible, enrollment error, registration failure, grade submission failure, student records unavailable, online classroom unavailable, cannot submit requirements, cannot upload assignments, internet unavailable, Wi-Fi disconnected, cannot connect to network, DNS server error, network authentication failure, frequent disconnections, LAN connection failure, computer won\'t boot, operating system failure, blue screen error, computer keeps restarting, printer unavailable, server connection failure, workstation malfunction, application keeps crashing, critical software malfunction',
            'medium' => 'medium, moderate, intermittent connection, slow loading, slow performance, system lag, delayed response, long loading time, application freezing, occasional crashing, high latency, slow processing, unresponsive interface, delayed synchronization, unstable Wi-Fi, weak signal, slow internet, frequent lag, packet loss, limited connectivity, connection timeout, temporary disconnection, network congestion, page not loading properly, missing information, incorrect display, upload delay, download issue, partial system error, search function not working, notification delay, dashboard not updating, broken links, minor portal error, incomplete records display, keyboard malfunction, mouse not responding, monitor flickering, printer paper jam, printer connection issue, scanner malfunction, headphone malfunction, microphone not working, webcam issue, USB port not working',
            'low' => '',
        ];

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.sla-settings'))
            ->assertOk()
            ->assertSee($defaultKeywords['urgent'])
            ->assertSee($defaultKeywords['high'])
            ->assertSee($defaultKeywords['medium']);

        $this->assertSame($defaultKeywords, app(TicketPriorityClassifier::class)->keywords());

        foreach ($defaultKeywords as $priority => $keywords) {
            $this->assertDatabaseHas('system_settings', [
                'setting' => "ticket_priority.{$priority}.keywords",
                'value' => $keywords,
            ]);
        }
    }

    public function test_rerunning_keyword_defaults_migration_preserves_existing_custom_values(): void
    {
        DB::table('system_settings')->where('setting', 'ticket_priority.urgent.keywords')->update([
            'value' => 'custom urgent phrase',
        ]);

        $migration = require database_path('migrations/2026_11_08_150616_seed_default_ticket_priority_keywords.php');
        $migration->up();

        $this->assertDatabaseHas('system_settings', [
            'setting' => 'ticket_priority.urgent.keywords',
            'value' => 'custom urgent phrase',
        ]);
    }

    public function test_admin_sees_default_sla_targets_before_customizing_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.sla-settings'))
            ->assertOk()
            ->assertSee('Save ticket options')
            ->assertSee('Save SLA targets')
            ->assertSee('Normal priority')
            ->assertDontSee('Medium priority')
            ->assertSee(route('admin.sla-settings.priority-keywords.update'), false)
            ->assertSee('name="targets[urgent][first_response][value]"', false)
            ->assertSee('name="priority_keywords[urgent]"', false)
            ->assertSee('Manage keywords')
            ->assertSee('data-keyword-input', false)
            ->assertSee('data-add-keyword', false)
            ->assertSee('data-keyword-list', false)
            ->assertSee('urgent, critical, emergency, outage')
            ->assertSee('value="1"', false)
            ->assertSee('Business hours')
            ->assertSee('Business days');
    }

    public function test_admin_can_update_sla_targets_and_see_saved_values(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $targets = [
            'urgent' => [
                'first_response' => ['value' => 2, 'unit' => 'business_hour'],
                'resolution' => ['value' => 6, 'unit' => 'business_hour'],
            ],
            'high' => [
                'first_response' => ['value' => 5, 'unit' => 'business_hour'],
                'resolution' => ['value' => 2, 'unit' => 'business_day'],
            ],
            'medium' => [
                'first_response' => ['value' => 2, 'unit' => 'business_day'],
                'resolution' => ['value' => 4, 'unit' => 'business_day'],
            ],
            'low' => [
                'first_response' => ['value' => 3, 'unit' => 'business_day'],
                'resolution' => ['value' => 6, 'unit' => 'business_day'],
            ],
        ];

        $this->actingAs($admin)
            ->put(route('admin.sla-settings.update'), ['targets' => $targets])
            ->assertRedirect(route('admin.sla-settings'))
            ->assertSessionHas('status', 'Service targets updated.');

        $this->assertDatabaseHas('system_settings', [
            'setting' => 'sla.urgent.first_response.value',
            'value' => '2',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'setting' => 'sla.low.resolution.unit',
            'value' => 'business_day',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.sla-settings'))
            ->assertOk()
            ->assertSee('value="2"', false)
            ->assertSee('value="6"', false);
    }

    public function test_invalid_sla_target_does_not_save_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $targets = [
            'urgent' => [
                'first_response' => ['value' => 0, 'unit' => 'business_hour'],
                'resolution' => ['value' => 4, 'unit' => 'business_hour'],
            ],
            'high' => [
                'first_response' => ['value' => 4, 'unit' => 'business_hour'],
                'resolution' => ['value' => 1, 'unit' => 'business_day'],
            ],
            'medium' => [
                'first_response' => ['value' => 1, 'unit' => 'business_day'],
                'resolution' => ['value' => 3, 'unit' => 'business_day'],
            ],
            'low' => [
                'first_response' => ['value' => 2, 'unit' => 'business_day'],
                'resolution' => ['value' => 5, 'unit' => 'business_day'],
            ],
        ];

        $this->actingAs($admin)
            ->from(route('admin.sla-settings'))
            ->put(route('admin.sla-settings.update'), ['targets' => $targets])
            ->assertRedirect(route('admin.sla-settings'))
            ->assertSessionHasErrors('targets.urgent.first_response.value');

        $this->assertDatabaseMissing('system_settings', [
            'setting' => 'sla.urgent.first_response.value',
        ]);
    }

    public function test_admin_can_update_automatic_priority_keywords(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $urgentFirstResponseTarget = app(SlaSettings::class)->targets()['urgent']['first_response'];
        $keywords = [
            'urgent' => 'campus-wide outage',
            'high' => 'cannot submit',
            'medium' => 'slow response',
            'low' => '',
        ];

        $this->actingAs($admin)
            ->put(route('admin.sla-settings.priority-keywords.update'), ['priority_keywords' => $keywords])
            ->assertRedirect(route('admin.sla-settings'))
            ->assertSessionHas('status', 'Priority keyword rules updated.');

        $this->assertDatabaseHas('system_settings', [
            'setting' => 'ticket_priority.urgent.keywords',
            'value' => 'campus-wide outage',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'setting' => 'ticket_priority.low.keywords',
            'value' => '',
        ]);
        $this->assertSame(
            $urgentFirstResponseTarget,
            app(SlaSettings::class)->targets()['urgent']['first_response'],
        );

        $department = Department::factory()->create();
        Notification::fake();
        $this->post(route('ticket.store'), [
            'name' => 'Jamie Student',
            'student_id' => '2023000001',
            'email' => 'jamie@example.edu',
            'department_id' => $department->id,
            'subject' => 'Campus-wide outage',
            'message' => 'A general service problem.',
        ])->assertRedirect(route('ticket.submitted'));

        $verificationUrl = null;
        Notification::assertSentOnDemand(
            TicketEmailVerification::class,
            function (TicketEmailVerification $notification, array $channels, AnonymousNotifiable $notifiable) use (&$verificationUrl): bool {
                $verificationUrl = $notification->verificationUrl;

                return $channels === ['mail']
                    && $notifiable->routeNotificationFor('mail') === 'jamie@example.edu';
            },
        );
        $this->assertNotNull($verificationUrl);
        $this->get($verificationUrl)->assertOk();
        $this->post($verificationUrl)->assertRedirect(route('ticket.submitted'));

        $this->assertDatabaseHas('tickets', [
            'subject' => 'Campus-wide outage',
            'priority' => 'Urgent',
        ]);

        $ticket = Ticket::query()->where('subject', 'Campus-wide outage')->firstOrFail();
        $triageEvent = $ticket->events()->where('event', 'automated_triage')->firstOrFail();

        $this->assertSame('campus-wide outage', $triageEvent->properties['priority_keyword']);
    }

    public function test_admin_sees_keyword_recommendations_from_repeated_priority_corrections(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $department = Department::factory()->create();
        $keywords = [
            'urgent' => 'campus-wide outage',
            'high' => 'high-impact failure',
            'medium' => 'moderate delay',
            'low' => '',
        ];
        app(TicketPriorityClassifier::class)->update($keywords);

        foreach (range(1, 3) as $index) {
            $ticket = $this->createPriorityFeedbackTicket(
                $department,
                "ED-2026-91000{$index}",
                'Slow campus network disconnects',
            );
            $ticket->events()->create([
                'event' => 'priority_changed',
                'description' => 'Priority changed to Medium.',
                'properties' => [
                    'priority_feedback' => true,
                    'previous_priority' => 'Low',
                    'new_priority' => 'Medium',
                    'matched_keyword' => null,
                ],
            ]);
        }

        foreach (range(1, 2) as $index) {
            $ticket = $this->createPriorityFeedbackTicket(
                $department,
                "ED-2026-92000{$index}",
                'Wireless printer request',
            );
            $ticket->events()->create([
                'event' => 'priority_changed',
                'description' => 'Priority changed to High.',
                'properties' => [
                    'priority_feedback' => true,
                    'previous_priority' => 'Low',
                    'new_priority' => 'High',
                    'matched_keyword' => null,
                ],
            ]);
        }

        foreach (range(1, 3) as $index) {
            $ticket = $this->createPriorityFeedbackTicket(
                $department,
                "ED-2026-93000{$index}",
                'Campus-wide outage affecting the portal',
                'Urgent',
            );
            $ticket->events()->create([
                'event' => 'priority_changed',
                'description' => 'Priority changed to High.',
                'properties' => [
                    'priority_feedback' => true,
                    'previous_priority' => 'Urgent',
                    'new_priority' => 'High',
                    'matched_keyword' => 'campus-wide outage',
                ],
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.sla-settings'))
            ->assertOk()
            ->assertSee('Suggestions from staff corrections')
            ->assertSee('slow campus network')
            ->assertSee('3 corrected tickets')
            ->assertDontSee('wireless printer')
            ->assertSee('data-suggestion-action="add"', false)
            ->assertSee('data-suggestion-action="remove"', false);
    }

    public function test_admin_does_not_see_priority_suggestion_after_later_correction_replaces_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $department = Department::factory()->create();

        foreach (range(1, 3) as $index) {
            $ticket = $this->createPriorityFeedbackTicket(
                $department,
                "ED-2026-95000{$index}",
                'Slow campus network disconnects',
            );
            $ticket->events()->create([
                'event' => 'priority_changed',
                'description' => 'Priority changed to Medium.',
                'properties' => [
                    'priority_feedback' => true,
                    'previous_priority' => 'Low',
                    'new_priority' => 'Medium',
                ],
            ]);

            if ($index === 1) {
                $ticket->events()->create([
                    'event' => 'priority_changed',
                    'description' => 'Priority restored to the original classification.',
                    'properties' => [
                        'priority_feedback' => false,
                        'previous_priority' => 'Medium',
                        'new_priority' => 'Low',
                    ],
                ]);
            }
        }

        $this->actingAs($admin)
            ->get(route('admin.sla-settings'))
            ->assertOk()
            ->assertDontSee('slow campus network');
    }

    public function test_admin_can_approve_category_phrase_suggestions_from_staff_corrections(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $department = Department::factory()->create();
        $category = Category::factory()->create([
            'name' => 'Account access',
            'description' => 'Login and credential support.',
            'department_id' => $department->id,
        ]);

        foreach (range(1, 3) as $index) {
            $ticket = Ticket::create([
                'ticket_number' => "ED-2026-94000{$index}",
                'department_id' => $department->id,
                'category_id' => $category->id,
                'requester_name' => 'Jamie Student',
                'student_id' => "202300000{$index}",
                'requester_email' => "jamie{$index}@example.edu",
                'subject' => 'Slow campus network disconnects',
                'description' => 'A test support request.',
                'priority' => 'Low',
                'status' => 'New',
            ]);
            $ticket->events()->create([
                'event' => 'category_changed',
                'description' => 'Ticket classification corrected by staff.',
                'properties' => [
                    'category_feedback' => true,
                    'new_category_id' => $category->id,
                ],
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.sla-settings'))
            ->assertOk()
            ->assertSee('Suggested phrases from staff corrections')
            ->assertSee('campus network disconnects')
            ->assertSee('3 corrected tickets');

        $this->patch(route('admin.sla-settings.categories.keywords.approve', $category), [
            'keyword' => 'campus network disconnects',
        ])
            ->assertRedirect(route('admin.sla-settings'))
            ->assertSessionHas('status', 'Category matching phrase approved.');

        $this->assertSame(
            'campus network disconnects',
            $category->fresh()->classification_keywords,
        );
        $this->assertSame(
            $category->id,
            app(TicketDepartmentClassifier::class)
                ->resolve('Campus network disconnects', '')
                ?->id,
        );
        $this->assertDatabaseHas('ticket_events', [
            'event' => 'category_keyword_approved',
            'subject_type' => 'Category',
            'subject_id' => (string) $category->id,
        ]);
    }

    public function test_admin_does_not_see_category_suggestion_after_later_correction_replaces_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $department = Department::factory()->create();
        $category = Category::factory()->create(['department_id' => $department->id]);

        foreach (range(1, 3) as $index) {
            $ticket = Ticket::create([
                'ticket_number' => "ED-2026-96000{$index}",
                'department_id' => $department->id,
                'category_id' => $category->id,
                'requester_name' => 'Jamie Student',
                'student_id' => "202400000{$index}",
                'requester_email' => "jamiereview{$index}@example.edu",
                'subject' => 'Campus wifi dropout',
                'description' => 'A test support request.',
                'priority' => 'Low',
                'status' => 'New',
            ]);
            $ticket->events()->create([
                'event' => 'category_changed',
                'description' => 'Ticket classification corrected by staff.',
                'properties' => [
                    'category_feedback' => true,
                    'new_category_id' => $category->id,
                ],
            ]);

            if ($index === 1) {
                $ticket->events()->create([
                    'event' => 'category_changed',
                    'description' => 'Category restored to the original classification.',
                    'properties' => [
                        'category_feedback' => false,
                        'new_category_id' => $category->id,
                    ],
                ]);
            }
        }

        $this->actingAs($admin)
            ->get(route('admin.sla-settings'))
            ->assertOk()
            ->assertDontSee('campus wifi');
    }

    public function test_staff_cannot_update_sla_targets(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $priorityKeywords = app(TicketPriorityClassifier::class)->keywords();

        $this->actingAs($staff)
            ->put(route('admin.sla-settings.update'), [])
            ->assertForbidden();
        $this->put(route('admin.sla-settings.priority-keywords.update'), [])
            ->assertForbidden();
        $this->patch(route('admin.sla-settings.categories.keywords.approve', Category::factory()->create()), [
            'keyword' => 'unapproved suggestion',
        ])->assertForbidden();

        $this->assertDatabaseMissing('system_settings', [
            'setting' => 'sla.urgent.first_response.value',
        ]);
        $this->assertSame($priorityKeywords, app(TicketPriorityClassifier::class)->keywords());
    }

    private function createPriorityFeedbackTicket(
        Department $department,
        string $ticketNumber,
        string $subject,
        string $priority = 'Low',
    ): Ticket {
        return Ticket::create([
            'ticket_number' => $ticketNumber,
            'department_id' => $department->id,
            'requester_name' => 'Jamie Student',
            'student_id' => $ticketNumber,
            'requester_email' => $ticketNumber.'@example.edu',
            'subject' => $subject,
            'description' => 'A test support request.',
            'priority' => $priority,
            'status' => 'New',
        ]);
    }
}
