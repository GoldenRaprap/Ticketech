<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketResolutionSla;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBusinessHoursSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_business_hours_and_recalculate_open_ticket_deadlines(): void
    {
        config(['app.display_timezone' => 'UTC', 'app.timezone' => 'UTC']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->travelTo('2026-11-02 08:00:00 UTC');
        $ticket = $this->createTicket();

        $this->actingAs($admin)
            ->get(route('admin.system-settings'))
            ->assertOk()
            ->assertSee('value="09:00"', false)
            ->assertSee('value="17:00"', false);

        $this->actingAs($admin)
            ->put(route('admin.system-settings.business-hours.update'), [
                'opening_time' => '10:00',
                'closing_time' => '17:30',
            ])
            ->assertRedirect(route('admin.system-settings'))
            ->assertSessionHas('status', 'Business hours updated.');

        $this->assertDatabaseHas('system_settings', [
            'setting' => 'sla.business_hours.opening_time',
            'value' => '10:00',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'setting' => 'sla.business_hours.closing_time',
            'value' => '17:30',
        ]);
        $this->assertSame(
            '2026-11-02 17:30:00',
            $ticket->fresh()->resolution_due_at->format('Y-m-d H:i:s'),
        );
        $this->assertSame(
            450,
            app(TicketResolutionSla::class)->businessMinutesBetween(
                CarbonImmutable::parse('2026-11-02 09:00:00 UTC'),
                CarbonImmutable::parse('2026-11-02 18:00:00 UTC'),
            ),
        );
        $this->assertDatabaseHas('ticket_events', [
            'actor_id' => $admin->id,
            'event' => 'business_hours_updated',
            'description' => 'Updated the business hours used for resolution targets.',
        ]);
    }

    public function test_admin_cannot_save_business_hours_that_close_before_they_open(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('admin.system-settings'))
            ->put(route('admin.system-settings.business-hours.update'), [
                'opening_time' => '17:00',
                'closing_time' => '09:00',
            ])
            ->assertRedirect(route('admin.system-settings'))
            ->assertSessionHasErrors([
                'closing_time' => 'The closing time must be later than the opening time.',
            ]);

        $this->assertDatabaseMissing('system_settings', [
            'setting' => 'sla.business_hours.opening_time',
        ]);
        $this->assertDatabaseMissing('system_settings', [
            'setting' => 'sla.business_hours.closing_time',
        ]);
    }

    public function test_business_hours_must_use_twenty_four_hour_time_values(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('admin.system-settings'))
            ->put(route('admin.system-settings.business-hours.update'), [
                'opening_time' => '9am',
                'closing_time' => '5pm',
            ])
            ->assertRedirect(route('admin.system-settings'))
            ->assertSessionHasErrors([
                'opening_time' => 'The opening time field must match the format H:i.',
                'closing_time' => 'The closing time field must match the format H:i.',
            ]);
    }

    public function test_business_hours_are_required(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('admin.system-settings'))
            ->put(route('admin.system-settings.business-hours.update'))
            ->assertRedirect(route('admin.system-settings'))
            ->assertSessionHasErrors([
                'opening_time' => 'The opening time field is required.',
                'closing_time' => 'The closing time field is required.',
            ]);
    }

    public function test_non_admin_cannot_update_business_hours(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->put(route('admin.system-settings.business-hours.update'), [
                'opening_time' => '08:00',
                'closing_time' => '16:00',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('system_settings', [
            'setting' => 'sla.business_hours.opening_time',
        ]);
    }

    private function createTicket(): Ticket
    {
        return Ticket::create([
            'ticket_number' => 'ED-2026-900001',
            'department_id' => Department::factory()->create()->id,
            'requester_name' => 'Jamie Student',
            'student_id' => 'PCU-001',
            'requester_email' => 'jamie@example.edu',
            'subject' => 'Support request',
            'description' => 'A test support request.',
            'priority' => 'High',
            'status' => 'New',
        ]);
    }
}
