<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Department;
use App\Models\PendingTicketSubmission;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketDepartmentClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDepartmentCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_departments_and_categories(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.sla-settings'))
            ->assertOk()
            ->assertSee('Departments and categories')
            ->assertSee('Add department')
            ->assertSee('Add category');

        $this->post(route('admin.sla-settings.departments.store'), [
            'department_name' => 'Information Technology',
        ])->assertRedirect(route('admin.sla-settings'));
        $this->post(route('admin.sla-settings.departments.store'), [
            'department_name' => 'Student Services',
        ])->assertRedirect(route('admin.sla-settings'));

        $department = Department::query()->where('name', 'Information Technology')->firstOrFail();
        $this->post(route('admin.sla-settings.categories.store'), [
            'category_name' => 'Account access',
            'description' => 'Login and account issues.',
            'classification_keywords' => 'login portal, student sign-in',
            'department_id' => $department->id,
        ])->assertRedirect(route('admin.sla-settings'));

        $category = Category::query()->where('name', 'Account access')->firstOrFail();

        $this->assertTrue($department->is_active);
        $this->assertTrue($category->is_active);
        $this->assertSame('Login and account issues.', $category->description);
        $this->assertSame('login portal, student sign-in', $category->classification_keywords);
        $this->assertSame($department->id, $category->department_id);

        $studentServices = Department::query()->where('name', 'Student Services')->firstOrFail();
        $this->patch(route('admin.sla-settings.categories.department.update', $category), [
            'department_id' => $studentServices->id,
        ])->assertRedirect(route('admin.sla-settings'));

        $this->assertSame($studentServices->id, $category->fresh()->department_id);

        $this->get(route('admin.sla-settings'))
            ->assertOk()
            ->assertSee('Information Technology')
            ->assertSee('Student Services')
            ->assertSee('Account access')
            ->assertSee('Login and account issues.')
            ->assertSee('Needs review (no route)');
    }

    public function test_admin_cannot_route_a_category_to_an_inactive_department(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $department = Department::factory()->create(['is_active' => false]);
        $category = Category::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.sla-settings'))
            ->patch(route('admin.sla-settings.categories.department.update', $category), [
                'department_id' => $department->id,
            ])
            ->assertRedirect(route('admin.sla-settings'))
            ->assertSessionHasErrors('department_id');

        $this->assertNull($category->fresh()->department_id);
    }

    public function test_admin_sees_new_category_suggestions_after_three_matching_unmatched_tickets(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $tickets = $this->createUnmatchedParkingPermitTickets(2);
        $tickets[0]->events()->create([
            'event' => 'automated_triage',
            'description' => 'Automated triage ran again for this ticket.',
            'properties' => ['routing_status' => 'needs_department'],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.sla-settings'))
            ->assertOk()
            ->assertDontSee('Recurring phrase: parking permit');

        $this->createUnmatchedParkingPermitTickets(1, 3);

        $this->get(route('admin.sla-settings'))
            ->assertOk()
            ->assertSee('Suggested new categories')
            ->assertSee('Recurring phrase:')
            ->assertSee('parking permit')
            ->assertSee('Parking permit payment not recognized')
            ->assertSee('Parking permit renewal confirmation missing')
            ->assertSee('Update vehicle parking permit')
            ->assertSee('Add an active department before approving this category.');
    }

    public function test_admin_does_not_get_a_new_category_suggestion_for_an_existing_phrase(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Category::factory()->create(['classification_keywords' => 'parking permit']);
        $this->createUnmatchedParkingPermitTickets(3);

        $this->actingAs($admin)
            ->get(route('admin.sla-settings'))
            ->assertOk()
            ->assertDontSee('Suggested new categories');
    }

    public function test_admin_can_create_a_suggested_category_and_route_matching_tickets(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $department = Department::factory()->create(['name' => 'Campus Services']);
        $tickets = $this->createUnmatchedParkingPermitTickets(3);

        $this->actingAs($admin)
            ->post(route('admin.sla-settings.category-suggestions.store'), [
                'keyword' => 'parking permit',
                'category_name' => 'Parking Permits',
                'department_id' => $department->id,
            ])
            ->assertRedirect(route('admin.sla-settings'))
            ->assertSessionHas('status', 'Created category Parking Permits and routed 3 unmatched tickets to Campus Services.');

        $category = Category::query()->where('name', 'Parking Permits')->firstOrFail();
        $this->assertSame('parking permit', $category->classification_keywords);
        $this->assertSame($department->id, $category->department_id);
        $this->assertTrue($category->is_active);

        foreach ($tickets as $ticket) {
            $this->assertDatabaseHas('tickets', [
                'id' => $ticket->id,
                'department_id' => $department->id,
                'category_id' => $category->id,
            ]);
            $this->assertDatabaseHas('ticket_events', [
                'ticket_id' => $ticket->id,
                'actor_id' => $admin->id,
                'event' => 'category_suggestion_applied',
            ]);
        }

        $this->assertDatabaseHas('ticket_events', [
            'event' => 'category_suggestion_approved',
            'subject_type' => 'Category',
            'subject_id' => (string) $category->id,
        ]);
        $this->assertSame(
            $category->id,
            app(TicketDepartmentClassifier::class)->resolve('Parking permit payment issue', '')?->id,
        );
        $this->get(route('admin.sla-settings'))
            ->assertOk()
            ->assertDontSee('Recurring phrase: parking permit');
    }

    public function test_admin_cannot_approve_a_stale_suggestion_or_use_an_inactive_department(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $inactiveDepartment = Department::factory()->create(['is_active' => false]);
        $tickets = $this->createUnmatchedParkingPermitTickets(3);

        $this->actingAs($admin)
            ->from(route('admin.sla-settings'))
            ->post(route('admin.sla-settings.category-suggestions.store'), [
                'keyword' => 'parking permit',
                'category_name' => 'Parking Permits',
                'department_id' => $inactiveDepartment->id,
            ])
            ->assertRedirect(route('admin.sla-settings'))
            ->assertSessionHasErrors('department_id');

        $this->assertDatabaseMissing('categories', ['name' => 'Parking Permits']);

        $tickets[0]->update(['status' => 'Closed']);
        $activeDepartment = Department::factory()->create();

        $this->post(route('admin.sla-settings.category-suggestions.store'), [
            'keyword' => 'parking permit',
            'category_name' => 'Parking Permits',
            'department_id' => $activeDepartment->id,
        ])
            ->assertRedirect(route('admin.sla-settings'))
            ->assertSessionHasErrors('keyword');

        $this->assertDatabaseMissing('categories', ['name' => 'Parking Permits']);
        $this->assertDatabaseHas('tickets', [
            'id' => $tickets[1]->id,
            'department_id' => null,
            'category_id' => null,
        ]);
    }

    public function test_admin_can_deactivate_and_reactivate_departments_and_categories(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $department = Department::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.sla-settings.departments.toggle', $department))
            ->assertRedirect(route('admin.sla-settings'));

        $this->patch(route('admin.sla-settings.categories.toggle', $category))
            ->assertRedirect(route('admin.sla-settings'));

        $this->assertDatabaseHas('departments', ['id' => $department->id, 'is_active' => false]);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'is_active' => false]);

        $this->patch(route('admin.sla-settings.departments.toggle', $department))
            ->assertRedirect(route('admin.sla-settings'));
        $this->patch(route('admin.sla-settings.categories.toggle', $category))
            ->assertRedirect(route('admin.sla-settings'));

        $this->assertDatabaseHas('departments', ['id' => $department->id, 'is_active' => true]);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'is_active' => true]);
    }

    public function test_staff_cannot_manage_departments_or_categories(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('admin.sla-settings'))
            ->assertForbidden();

        $this->post(route('admin.sla-settings.departments.store'), [
            'department_name' => 'Unauthorized department',
        ])->assertForbidden();
        $this->patch(route('admin.sla-settings.categories.department.update', Category::factory()->create()))
            ->assertForbidden();
        $this->post(route('admin.sla-settings.category-suggestions.store'), [
            'keyword' => 'parking permit',
            'category_name' => 'Parking Permits',
            'department_id' => 1,
        ])->assertForbidden();

        $this->assertDatabaseCount('departments', 0);
    }

    public function test_ticket_form_automatically_selects_category_and_department(): void
    {
        $department = Department::factory()->create(['name' => 'Student Services']);
        $inactiveDepartment = Department::factory()->create(['name' => 'Inactive Department', 'is_active' => false]);
        $category = Category::factory()->create([
            'name' => 'Portal access',
            'department_id' => $department->id,
        ]);
        $inactiveCategory = Category::factory()->create(['name' => 'Inactive Category', 'is_active' => false]);

        $this->get(route('ticket.create'))
            ->assertOk()
            ->assertDontSee('name="department_id"', false)
            ->assertDontSee('name="category_id"', false)
            ->assertSee('category and department are selected automatically')
            ->assertDontSee('Inactive Department')
            ->assertDontSee('Student Services')
            ->assertDontSee('Inactive Category');

        $this->post(route('ticket.store'), [
            'name' => 'Jamie Student',
            'student_id' => '2023000001',
            'email' => 'jamie@example.edu',
            'department_id' => $department->id,
            'category_id' => $category->id,
            'subject' => 'Cannot access the student portal',
            'message' => 'The student portal shows an error.',
        ])->assertRedirect(route('ticket.submitted'));

        $submission = PendingTicketSubmission::query()->firstOrFail();
        $this->assertSame(null, $submission->payload['department_id']);
        $this->assertSame(null, $submission->payload['category_id']);
        $this->assertDatabaseMissing('tickets', [
            'department_id' => $department->id,
        ]);
        $this->assertDatabaseMissing('tickets', [
            'department_id' => $inactiveDepartment->id,
        ]);
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_ticket_submission_ignores_requester_supplied_category_and_department(): void
    {
        $department = Department::factory()->create(['is_active' => false]);
        $category = Category::factory()->create([
            'name' => 'Student portal access',
            'is_active' => false,
        ]);

        $this->from(route('ticket.create'))
            ->post(route('ticket.store'), [
                'name' => 'Jamie Student',
                'student_id' => '2023000001',
                'email' => 'jamie@example.edu',
                'department_id' => $department->id,
                'category_id' => $category->id,
                'subject' => 'Student portal access problem',
                'message' => 'I cannot sign in to the student portal.',
            ])
            ->assertRedirect(route('ticket.submitted'))
            ->assertSessionDoesntHaveErrors(['category_id', 'department_id']);

        $submission = PendingTicketSubmission::query()->firstOrFail();
        $this->assertSame(null, $submission->payload['category_id']);
        $this->assertSame(null, $submission->payload['department_id']);

        $this->assertDatabaseCount('tickets', 0);
    }

    /**
     * @return list<Ticket>
     */
    private function createUnmatchedParkingPermitTickets(int $count, int $startingIndex = 1): array
    {
        $subjects = [
            'Parking permit payment not recognized',
            'Parking permit renewal confirmation missing',
            'Update vehicle parking permit',
        ];
        $tickets = [];

        for ($index = $startingIndex; $index < $startingIndex + $count; $index++) {
            $ticket = Ticket::create([
                'ticket_number' => sprintf('ED-2026-PARK-%04d', $index),
                'requester_name' => 'Jamie Student',
                'student_id' => sprintf('202300%04d', $index),
                'requester_email' => "parking{$index}@example.edu",
                'subject' => $subjects[($index - 1) % count($subjects)],
                'description' => 'A sample request that has not been classified.',
                'priority' => 'Medium',
                'status' => 'New',
            ]);
            $ticket->events()->create([
                'event' => 'automated_triage',
                'description' => 'Automated triage could not match this ticket.',
                'properties' => ['routing_status' => 'needs_department'],
            ]);
            $tickets[] = $ticket;
        }

        return $tickets;
    }
}
