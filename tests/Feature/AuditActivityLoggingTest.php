<?php

namespace Tests\Feature;

use App\Models\TicketEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditActivityLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_admin_sign_in_is_recorded(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.edu',
            'password' => 'correct-password',
            'role' => 'admin',
        ]);

        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('ticket_events', [
            'actor_id' => $admin->id,
            'actor_name' => $admin->name,
            'event' => 'login_succeeded',
            'request_method' => 'POST',
            'route_name' => 'login.store',
        ]);
    }

    public function test_failed_sign_in_is_recorded_without_the_password(): void
    {
        $this->post(route('login.store'), [
            'email' => 'unknown@example.edu',
            'password' => 'do-not-store-this-password',
        ])->assertSessionHasErrors('email');

        $event = TicketEvent::query()->where('event', 'login_failed')->firstOrFail();

        $this->assertNull($event->actor_id);
        $this->assertSame(['email' => 'unknown@example.edu'], $event->properties);
        $this->assertStringNotContainsString('do-not-store-this-password', json_encode($event->toArray()));
    }

    public function test_admin_page_access_records_actor_route_and_request_metadata(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        $event = TicketEvent::query()
            ->where('event', 'admin_page_viewed')
            ->where('route_name', 'admin.dashboard')
            ->firstOrFail();

        $this->assertSame($admin->id, $event->actor_id);
        $this->assertSame($admin->name, $event->actor_name);
        $this->assertSame('GET', $event->request_method);
        $this->assertNotEmpty($event->ip_address);
        $this->assertSame('completed', $event->properties['outcome']);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs'))
            ->assertOk()
            ->assertSee('Admin Page Viewed')
            ->assertSee('admin.dashboard')
            ->assertSee($admin->name)
            ->assertSee($event->ip_address)
            ->assertSee('data-auto-submit-on-change', false);
    }

    public function test_audit_log_uses_manila_time_and_filters_by_manila_calendar_day(): void
    {
        $this->travelTo('2026-01-01 00:00:00 UTC');
        config()->set('app.display_timezone', 'Asia/Manila');
        $admin = User::factory()->create(['role' => 'admin']);

        $beforeUtcMidnight = TicketEvent::create([
            'event' => 'boundary_before',
            'description' => 'Manila day begins before UTC midnight',
        ]);
        $beforeUtcMidnight->forceFill([
            'created_at' => '2026-11-09 16:30:00',
            'updated_at' => '2026-11-09 16:30:00',
        ])->save();
        $afterUtcMidnight = TicketEvent::create([
            'event' => 'boundary_after',
            'description' => 'Manila day ends after UTC midnight',
        ]);
        $afterUtcMidnight->forceFill([
            'created_at' => '2026-11-10 15:59:59',
            'updated_at' => '2026-11-10 15:59:59',
        ])->save();
        $nextManilaDay = TicketEvent::create([
            'event' => 'next_manila_day',
            'description' => 'Belongs to the next Manila day',
        ]);
        $nextManilaDay->forceFill([
            'created_at' => '2026-11-10 16:00:00',
            'updated_at' => '2026-11-10 16:00:00',
        ])->save();

        $response = $this->actingAs($admin)
            ->get(route('admin.audit-logs', ['date' => '2026-11-10']))
            ->assertOk()
            ->assertSee('Nov 10, 2026')
            ->assertSee('12:30 AM')
            ->assertSee('Manila day begins before UTC midnight')
            ->assertSee('Manila day ends after UTC midnight')
            ->assertDontSee('Belongs to the next Manila day');

        $this->assertSame(
            [$afterUtcMidnight->id, $beforeUtcMidnight->id],
            $response->viewData('events')->getCollection()->modelKeys(),
        );
    }

    public function test_staff_attempts_to_open_admin_pages_are_recorded_as_denied(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('admin.audit-logs'))
            ->assertForbidden();

        $event = TicketEvent::query()
            ->where('actor_id', $staff->id)
            ->where('route_name', 'admin.audit-logs')
            ->firstOrFail();

        $this->assertSame('staff_page_viewed', $event->event);
        $this->assertSame('failed', $event->properties['outcome']);
        $this->assertSame(403, $event->properties['response_status']);
    }

    public function test_staff_account_changes_record_safe_before_and_after_values(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create([
            'name' => 'Current Staff',
            'email' => 'current.staff@example.edu',
            'role' => 'staff',
            'employment_status' => 'active',
        ]);

        $this->actingAs($admin)->put(route('admin.users.update', $staff), [
            'staff' => [
                $staff->id => [
                    'name' => 'Former Staff',
                    'email' => 'former.staff@example.edu',
                    'employment_status' => 'inactive',
                ],
            ],
        ])->assertRedirect(route('admin.users'));

        $event = TicketEvent::query()
            ->where('event', 'staff_account_updated')
            ->firstOrFail();

        $this->assertSame($staff->id, (int) $event->subject_id);
        $this->assertSame('Current Staff', $event->properties['before']['name']);
        $this->assertSame('active', $event->properties['before']['employment_status']);
        $this->assertSame('Former Staff', $event->properties['after']['name']);
        $this->assertSame('inactive', $event->properties['after']['employment_status']);
        $this->assertArrayNotHasKey('password', $event->properties['before']);
        $this->assertArrayNotHasKey('password', $event->properties['after']);
    }

    public function test_sign_out_is_recorded_before_the_session_ends(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->post(route('logout'))->assertRedirect(route('home'));

        $this->assertDatabaseHas('ticket_events', [
            'actor_id' => $staff->id,
            'event' => 'logout',
            'request_method' => 'POST',
            'route_name' => 'logout',
        ]);
    }
}
