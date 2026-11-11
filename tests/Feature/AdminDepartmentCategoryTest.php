<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Department;
use App\Models\PendingTicketSubmission;
use App\Models\User;
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

        $this->post(route('admin.sla-settings.categories.store'), [
            'category_name' => 'Account access',
            'description' => 'Login and account issues.',
        ])->assertRedirect(route('admin.sla-settings'));

        $department = Department::query()->where('name', 'Information Technology')->firstOrFail();
        $category = Category::query()->where('name', 'Account access')->firstOrFail();

        $this->assertTrue($department->is_active);
        $this->assertTrue($category->is_active);
        $this->assertSame('Login and account issues.', $category->description);

        $this->get(route('admin.sla-settings'))
            ->assertOk()
            ->assertSee('Information Technology')
            ->assertSee('Account access')
            ->assertSee('Login and account issues.');
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

        $this->assertDatabaseCount('departments', 0);
    }

    public function test_ticket_form_does_not_allow_requesters_to_choose_a_department(): void
    {
        $department = Department::factory()->create(['name' => 'Student Services']);
        $inactiveDepartment = Department::factory()->create(['name' => 'Inactive Department', 'is_active' => false]);
        $category = Category::factory()->create(['name' => 'Portal access']);
        $inactiveCategory = Category::factory()->create(['name' => 'Inactive Category', 'is_active' => false]);

        $this->get(route('ticket.create'))
            ->assertOk()
            ->assertDontSee('name="department_id"', false)
            ->assertSee('name="category_id"', false)
            ->assertSee('>Portal access</option>', false)
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
        $this->assertSame($category->id, $submission->payload['category_id']);
        $this->assertDatabaseMissing('tickets', [
            'department_id' => $department->id,
        ]);
        $this->assertDatabaseMissing('tickets', [
            'department_id' => $inactiveDepartment->id,
        ]);
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_ticket_submission_rejects_deactivated_categories_but_does_not_validate_requester_department(): void
    {
        $department = Department::factory()->create(['is_active' => false]);
        $category = Category::factory()->create(['is_active' => false]);

        $this->from(route('ticket.create'))
            ->post(route('ticket.store'), [
                'name' => 'Jamie Student',
                'student_id' => '2023000001',
                'email' => 'jamie@example.edu',
                'department_id' => $department->id,
                'category_id' => $category->id,
                'subject' => 'Cannot access the student portal',
                'message' => 'The student portal shows an error.',
            ])
            ->assertRedirect(route('ticket.create'))
            ->assertSessionHasErrors(['category_id'])
            ->assertSessionDoesntHaveErrors(['department_id']);

        $this->assertDatabaseCount('pending_ticket_submissions', 0);
        $this->assertDatabaseCount('tickets', 0);
    }
}
