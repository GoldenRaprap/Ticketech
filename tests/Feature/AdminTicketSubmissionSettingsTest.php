<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTicketSubmissionSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_pause_ticket_submission_with_a_public_maintenance_message(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $maintenanceMessage = 'We are updating the student portal. Please check back soon.';

        $this->actingAs($admin)
            ->put(route('admin.system-settings.ticket-submission.update'), [
                'submission_enabled' => '0',
                'submission_message' => $maintenanceMessage,
            ])
            ->assertRedirect(route('admin.system-settings'))
            ->assertSessionHas('status', 'Public ticket submission is paused.');

        $this->assertDatabaseHas('system_settings', [
            'setting' => 'ticket.submission.enabled',
            'value' => '0',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'setting' => 'ticket.submission.message',
            'value' => $maintenanceMessage,
        ]);

        $this->get(route('admin.system-settings'))
            ->assertOk()
            ->assertSee('Maintenance')
            ->assertSee('role="switch" aria-label="Enable ticket submissions" data-public-feature-toggle', false)
            ->assertSee('admin-ticket-submission-switch-state admin-access-state--inactive', false)
            ->assertSee('data-public-feature-state>Disabled', false)
            ->assertSee('<summary class="admin-ticket-submission-summary">', false)
            ->assertSee('<details class="admin-ticket-message-details" >', false)
            ->assertSee('<textarea id="ticket-submission-message" name="submission_message"', false)
            ->assertSee('Save ticket availability')
            ->assertSee($maintenanceMessage);

        $this->get(route('ticket.create'))
            ->assertOk()
            ->assertSee('Ticket submission is temporarily paused')
            ->assertSee($maintenanceMessage)
            ->assertDontSee('name="subject"', false)
            ->assertSee(route('ticket.check'));

        $this->from(route('ticket.create'))->post(route('ticket.store'), [])
            ->assertRedirect(route('ticket.create'));

        $this->assertDatabaseCount('tickets', 0);
        $this->get(route('ticket.check'))->assertOk();
    }

    public function test_admin_can_reopen_ticket_submission_after_maintenance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('admin.system-settings.ticket-submission.update'), [
            'submission_enabled' => '0',
            'submission_message' => 'Maintenance in progress.',
        ])->assertRedirect(route('admin.system-settings'));

        $this->put(route('admin.system-settings.ticket-submission.update'), [
            'submission_enabled' => '1',
            'submission_message' => 'Maintenance is complete.',
        ])->assertRedirect(route('admin.system-settings'));

        $this->assertDatabaseHas('system_settings', [
            'setting' => 'ticket.submission.enabled',
            'value' => '1',
        ]);

        $this->get(route('ticket.create'))
            ->assertOk()
            ->assertSee('name="subject"', false)
            ->assertDontSee('Ticket submission is temporarily paused');

        $this->get(route('admin.system-settings'))
            ->assertOk()
            ->assertSee('aria-label="Enable ticket submissions" data-public-feature-toggle', false)
            ->assertSee('name="submission_enabled" type="checkbox" value="1" role="switch" aria-label="Enable ticket submissions" data-public-feature-toggle data-disable-title="Disable ticket submissions?" data-enabled-description="Visitors can submit a support request without an account." data-disabled-description="New ticket submissions are paused. Existing tickets remain available." data-disable-confirmation="Visitors will not be able to create new tickets." checked', false)
            ->assertSee('<details class="admin-ticket-message-details" >', false)
            ->assertSee('data-public-feature-state>Enabled', false)
            ->assertSee('Message shown while submissions are paused');
    }

    public function test_non_admins_cannot_change_ticket_submission_availability(): void
    {
        $this->put(route('admin.system-settings.ticket-submission.update'), [
            'submission_enabled' => '0',
            'submission_message' => 'Maintenance in progress.',
        ])->assertRedirect(route('login'));

        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->put(route('admin.system-settings.ticket-submission.update'), [
                'submission_enabled' => '0',
                'submission_message' => 'Maintenance in progress.',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('system_settings', 0);
    }

    public function test_pausing_ticket_submission_requires_a_customer_message(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->from(route('admin.system-settings'))
            ->put(route('admin.system-settings.ticket-submission.update'), [
                'submission_enabled' => '0',
                'submission_message' => '',
            ])
            ->assertRedirect(route('admin.system-settings'))
            ->assertSessionHasErrors(['submission_message']);

        $this->assertDatabaseCount('system_settings', 0);
    }
}
