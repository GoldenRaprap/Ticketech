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
                    'employment_status' => 'retired',
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
        $this->assertSame('retired', $event->properties['after']['employment_status']);
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
