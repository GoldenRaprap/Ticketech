<?php

namespace Tests\Feature;

use App\Models\Department;
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
            ->assertSee('<a class="public-brand" href="'.route('home').'">PCU Help Center</a>', false)
            ->assertSee('How can we help you today?')
            ->assertSee('No account needed.')
            ->assertSee('Submit a Ticket')
            ->assertSee('View Your Tickets')
            ->assertSee('<span class="public-divider" aria-hidden="true">|</span>', false)
            ->assertSee('Staff sign in')
            ->assertDontSee('Register')
            ->assertDontSee('Login');
    }

    public function test_requester_only_sees_their_own_recent_tickets(): void
    {
        $department = Department::create(['name' => 'IT Services', 'is_active' => true]);
        $requester = User::factory()->create();
        $otherRequester = User::factory()->create();
        $requester->tickets()->create([
            'ticket_number' => 'ED-2026-000101',
            'department_id' => $department->id,
            'requester_name' => $requester->name,
            'student_id' => 'PCU-001',
            'requester_email' => $requester->email,
            'subject' => 'My course portal',
            'description' => 'Cannot access course portal.',
        ]);
        $otherRequester->tickets()->create([
            'ticket_number' => 'ED-2026-000102',
            'department_id' => $department->id,
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
}
