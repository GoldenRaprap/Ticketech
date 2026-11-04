<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Notifications\TicketEmailVerification;
use App\Services\SlaSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminSlaSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_default_sla_targets_before_customizing_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.sla-settings'))
            ->assertOk()
            ->assertSee('Save ticket options')
            ->assertSee('name="targets[urgent][first_response][value]"', false)
            ->assertSee('name="priority_keywords[urgent]"', false)
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
            ->assertSessionHas('status', 'Ticket options updated.');

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
        $targets = app(SlaSettings::class)->targets();
        $keywords = [
            'urgent' => 'campus-wide outage',
            'high' => 'cannot submit',
            'medium' => 'slow response',
            'low' => '',
        ];

        $this->actingAs($admin)
            ->put(route('admin.sla-settings.update'), [
                'targets' => $targets,
                'priority_keywords' => $keywords,
            ])
            ->assertRedirect(route('admin.sla-settings'))
            ->assertSessionHas('status', 'Ticket options updated.');

        $this->assertDatabaseHas('system_settings', [
            'setting' => 'ticket_priority.urgent.keywords',
            'value' => 'campus-wide outage',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'setting' => 'ticket_priority.low.keywords',
            'value' => '',
        ]);

        $department = Department::factory()->create();
        Notification::fake();
        $this->post(route('ticket.store'), [
            'name' => 'Jamie Student',
            'student_id' => 'PCU-2026-001',
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
    }

    public function test_staff_cannot_update_sla_targets(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->put(route('admin.sla-settings.update'), [])
            ->assertForbidden();

        $this->assertDatabaseMissing('system_settings', [
            'setting' => 'sla.urgent.first_response.value',
        ]);
        $this->assertDatabaseMissing('system_settings', [
            'setting' => 'ticket_priority.urgent.keywords',
        ]);
    }
}
