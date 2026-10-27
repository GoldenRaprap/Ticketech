<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_helpdesk_actions(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<img src="'.asset('images/Philippine_Christian_University_logo.png').'" alt="Philippine Christian University logo">', false)
            ->assertSee('<span>PCU Helpdesk</span>', false)
            ->assertSee('How can we help you today?')
            ->assertSee('No account needed.')
            ->assertSee('Submit a Ticket')
            ->assertSee('View Your Tickets')
            ->assertSee('>Sign in</a>', false)
            ->assertDontSee('>Submit a Ticket</a>', false)
            ->assertDontSee('>View Your Tickets</a>', false)
            ->assertDontSee('Staff and administrator sign in')
            ->assertDontSee('Register')
            ->assertDontSee('Login');
    }

    public function test_homepage_is_also_available_at_the_helpdesk_url(): void
    {
        $this->get('/Helpdesk')
            ->assertOk()
            ->assertSee('How can we help you today?')
            ->assertSee('Submit a Ticket')
            ->assertSee('View Your Tickets');
    }

    public function test_requester_only_sees_their_own_recent_tickets(): void
    {
        $requester = User::factory()->create();
        $otherRequester = User::factory()->create();
        $requester->tickets()->create([
            'ticket_number' => 'ED-2026-000101',
            'requester_name' => $requester->name,
            'student_id' => 'PCU-001',
            'requester_email' => $requester->email,
            'subject' => 'My course portal',
            'description' => 'Cannot access course portal.',
        ]);
        $otherRequester->tickets()->create([
            'ticket_number' => 'ED-2026-000102',
            'requester_name' => $otherRequester->name,
            'student_id' => 'PCU-002',
            'requester_email' => $otherRequester->email,
            'subject' => 'Private request title',
            'description' => 'Private request contents.',
        ]);

        $this->actingAs($requester)->get(route('home'))
            ->assertOk()
            ->assertSee('ED-2026-000101')
            ->assertSee('My course portal')
            ->assertDontSee('ED-2026-000102')
            ->assertDontSee('Private request title');
    }

    public function test_staff_homepage_redirects_to_the_staff_dashboard(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get(route('home'))
            ->assertRedirect(route('staff.dashboard'));
    }

    public function test_admin_helpdesk_url_redirects_to_the_admin_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('helpdesk'))
            ->assertRedirect(route('admin.dashboard'));
    }
}
