<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ClientModeration;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketEvent;
use App\Models\User;
use App\Notifications\TicketEmailVerification;
use App\Services\TicketPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.turnstile.site_key', '');
        config()->set('services.turnstile.secret_key', '');
    }

    public function test_guests_can_open_ticket_submission_and_lookup_without_signing_in(): void
    {
        $this->get(route('ticket.create'))
            ->assertOk()
            ->assertSee('No account is needed.')
            ->assertSee('e.g. James Smith')
            ->assertSee('Student or Employee ID:')
            ->assertSee('pattern="[0-9]{10}"', false)
            ->assertSee('Enter exactly 10 digits.')
            ->assertSee('example@pcu.edu.ph')
            ->assertSee('e.g. 09171234567')
            ->assertSee('pattern="09[0-9]{9}"', false)
            ->assertSee('11 digits starting with 09.')
            ->assertSee('Write like an email')
            ->assertDontSee('name="priority"', false)
            ->assertDontSee('Urgent — Critical issue')
            ->assertSee('name="attachment[]"', false)
            ->assertSee('0</span> / 5 files selected', false)
            ->assertSee('Attach up to 5')
            ->assertSee('multiple', false)
            ->assertDontSee('name="department_id"', false)
            ->assertDontSee('name="category_id"', false)
            ->assertSee('category and department are selected automatically')
            ->assertSee('data-article-recommendations', false);

        $this->get(route('ticket.check'))
            ->assertOk()
            ->assertSee('No account is needed.');

        $this->assertGuest();
        $this->assertSame(true, Schema::hasColumn('tickets', 'department_id'));
        $this->assertSame(true, Schema::hasColumn('tickets', 'category_id'));
    }

    public function test_ticket_form_renders_turnstile_without_exposing_the_secret_key(): void
    {
        config()->set('services.turnstile.site_key', 'test-site-key');
        config()->set('services.turnstile.secret_key', 'test-secret-key');

        $this->get(route('ticket.create'))
            ->assertOk()
            ->assertSee('class="cf-turnstile" data-sitekey="test-site-key"', false)
            ->assertSee('https://challenges.cloudflare.com/turnstile/v0/api.js', false)
            ->assertDontSee('test-secret-key', false);
    }

    public function test_ticket_submission_stays_blocked_when_turnstile_is_partially_configured(): void
    {
        config()->set('services.turnstile.site_key', 'test-site-key');
        config()->set('services.turnstile.secret_key', '');
        Http::preventStrayRequests();
        Notification::fake();

        $this->get(route('ticket.create'))
            ->assertOk()
            ->assertSee('bot protection is not fully configured')
            ->assertSee('disabled', false);

        $this->from(route('ticket.create'))
            ->post(route('ticket.store'), [
                'name' => 'Jamie Student',
                'student_id' => '2023000001',
                'email' => 'jamie@example.edu',
                'subject' => 'Cannot access Bluebook',
                'message' => 'The course page returns an error.',
                'cf-turnstile-response' => 'unverified-token',
            ])
            ->assertRedirect(route('ticket.create'))
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->assertDatabaseCount('pending_ticket_submissions', 0);
        Http::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_ticket_submission_requires_a_turnstile_token_when_keys_are_configured(): void
    {
        config()->set('services.turnstile.site_key', 'test-site-key');
        config()->set('services.turnstile.secret_key', 'test-secret-key');
        Http::preventStrayRequests();
        Notification::fake();

        $this->from(route('ticket.create'))
            ->post(route('ticket.store'), [
                'name' => 'Jamie Student',
                'student_id' => '2023000001',
                'email' => 'jamie@example.edu',
                'subject' => 'Cannot access Bluebook',
                'message' => 'The course page returns an error.',
            ])
            ->assertRedirect(route('ticket.create'))
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->assertDatabaseCount('pending_ticket_submissions', 0);
        Http::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_ticket_submission_creates_a_pending_request_after_turnstile_verification(): void
    {
        config()->set('services.turnstile.site_key', 'test-site-key');
        config()->set('services.turnstile.secret_key', 'test-secret-key');
        Http::preventStrayRequests();
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => true,
            ]),
        ]);
        Notification::fake();

        $this->post(route('ticket.store'), [
            'name' => 'Jamie Student',
            'student_id' => '2023000001',
            'email' => 'jamie@example.edu',
            'subject' => 'Cannot access Bluebook',
            'message' => 'The course page returns an error.',
            'cf-turnstile-response' => 'valid-turnstile-token',
        ])->assertRedirect(route('ticket.submitted'));

        $this->assertDatabaseCount('pending_ticket_submissions', 1);
        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
            && $request['secret'] === 'test-secret-key'
        && $request['response'] === 'valid-turnstile-token');
        Notification::assertSentOnDemand(TicketEmailVerification::class);
    }

    public function test_ticket_submission_is_rejected_when_turnstile_verification_fails(): void
    {
        config()->set('services.turnstile.site_key', 'test-site-key');
        config()->set('services.turnstile.secret_key', 'test-secret-key');
        Http::preventStrayRequests();
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => false,
            ]),
        ]);
        Notification::fake();

        $this->from(route('ticket.create'))
            ->post(route('ticket.store'), [
                'name' => 'Jamie Student',
                'student_id' => '2023000001',
                'email' => 'jamie@example.edu',
                'subject' => 'Cannot access Bluebook',
                'message' => 'The course page returns an error.',
                'cf-turnstile-response' => 'invalid-turnstile-token',
            ])
            ->assertRedirect(route('ticket.create'))
            ->assertSessionHasErrors([
                'cf-turnstile-response' => 'Verification failed or expired. Please try again.',
            ]);

        $this->assertDatabaseCount('pending_ticket_submissions', 0);
        Notification::assertNothingSent();
    }

    public function test_ticket_submission_is_rejected_when_turnstile_is_unavailable(): void
    {
        config()->set('services.turnstile.site_key', 'test-site-key');
        config()->set('services.turnstile.secret_key', 'test-secret-key');
        Http::preventStrayRequests();
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::failedConnection(),
        ]);
        Notification::fake();

        $this->from(route('ticket.create'))
            ->post(route('ticket.store'), [
                'name' => 'Jamie Student',
                'student_id' => '2023000001',
                'email' => 'jamie@example.edu',
                'subject' => 'Cannot access Bluebook',
                'message' => 'The course page returns an error.',
                'cf-turnstile-response' => 'valid-turnstile-token',
            ])
            ->assertRedirect(route('ticket.create'))
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->assertDatabaseCount('pending_ticket_submissions', 0);
        Notification::assertNothingSent();
    }

    public function test_guest_submission_creates_a_ticket_with_private_attachments(): void
    {
        Storage::fake('private');
        config()->set('app.display_timezone', 'Asia/Manila');
        $this->travelTo('2026-10-27 10:00:00');
        $department = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $departmentAdmin = User::factory()->create([
            'role' => 'department_admin',
            'department_id' => $department->id,
        ]);
        $otherDepartmentAdmin = User::factory()->create([
            'role' => 'department_admin',
            'department_id' => $otherDepartment->id,
        ]);
        $departmentStaff = User::factory()->create([
            'role' => 'staff',
            'department_id' => $department->id,
        ]);
        $globalAdmin = User::factory()->create(['role' => 'admin']);
        $category = Category::factory()->create();

        Notification::fake();
        $response = $this->post(route('ticket.store'), [
            'name' => 'Jamie Student',
            'student_id' => '2023000001',
            'email' => 'jamie@example.edu',
            'phone' => '09171234567',
            'department_id' => $department->id,
            'category_id' => $category->id,
            'subject' => 'Cannot access Bluebook',
            'message' => 'The course page returns an error during an urgent outage.',
            'priority' => 'Urgent',
            'attachment' => [
                UploadedFile::fake()->create('proof.pdf', 50, 'application/pdf'),
                UploadedFile::fake()->create('screenshot.jpg', 50, 'image/jpeg'),
                UploadedFile::fake()->create('notes.doc', 50, 'application/msword'),
                UploadedFile::fake()->create('report.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                UploadedFile::fake()->create('image.png', 50, 'image/png'),
            ],
        ]);

        $response->assertRedirect(route('ticket.submitted'));
        $this->confirmPendingEmail();
        $ticket = Ticket::with('attachments')->firstOrFail();
        $this->assertMatchesRegularExpression('/^ED-\d{4}-\d{6}$/', $ticket->ticket_number);
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'requester_email' => 'jamie@example.edu',
            'department_id' => null,
            'category_id' => null,
            'priority' => 'Urgent',
            'status' => 'New',
        ]);
        $this->assertDatabaseHas('workspace_notifications', [
            'user_id' => $departmentAdmin->id,
            'ticket_id' => $ticket->id,
            'type' => 'new_ticket',
        ]);
        $this->assertDatabaseHas('workspace_notifications', [
            'user_id' => $globalAdmin->id,
            'ticket_id' => $ticket->id,
            'type' => 'new_ticket',
        ]);
        $this->assertDatabaseHas('workspace_notifications', [
            'user_id' => $otherDepartmentAdmin->id,
            'ticket_id' => $ticket->id,
            'type' => 'new_ticket',
        ]);
        $this->assertDatabaseMissing('workspace_notifications', [
            'user_id' => $departmentStaff->id,
            'ticket_id' => $ticket->id,
            'type' => 'new_ticket',
        ]);
        $this->assertCount(5, $ticket->attachments);
        $submittedFileNames = ['proof.pdf', 'screenshot.jpg', 'notes.doc', 'report.docx', 'image.png'];
        foreach ($ticket->attachments as $attachment) {
            Storage::disk('private')->assertExists($attachment->path);
            $this->assertSame(basename($attachment->path), $attachment->original_name);
            $this->assertNotContains($attachment->original_name, $submittedFileNames);
        }

        $this->get(route('ticket.submitted'))
            ->assertOk()
            ->assertSee('class="help-scene ticket-submitted-page"', false)
            ->assertSee('Ticket Submitted')
            ->assertSee($ticket->ticket_number)
            ->assertSee('October 27, 2026 6:00 PM')
            ->assertSee('name="print" value="1"', false)
            ->assertSee('Print ticket')
            ->assertSee('Download ticket')
            ->assertSee('Check Ticket Status');

        $this->get(route('ticket.check'))
            ->assertOk()
            ->assertSee('value="'.$ticket->ticket_number.'"', false)
            ->assertSee('value="jamie@example.edu"', false)
            ->assertViewHas('lookupTicketNumber', $ticket->ticket_number)
            ->assertViewHas('lookupEmail', 'jamie@example.edu');

        $downloadResponse = $this->post(route('ticket.download'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'jamie@example.edu',
        ]);
        $downloadResponse->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="ticket-'.strtolower($ticket->ticket_number).'.html"')
            ->assertDontSee('Priority')
            ->assertDontSee($ticket->priority)
            ->assertSee('Ticket details')
            ->assertSee('Client information')
            ->assertSee('Ticket conversation')
            ->assertSee('Not available')
            ->assertSee('Cannot access Bluebook')
            ->assertSee('October 27, 2026 at 6:00 PM')
            ->assertSee('The course page returns an error during an urgent outage.')
            ->assertSee('Jamie Student');

        $printResponse = $this->post(route('ticket.download'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'jamie@example.edu',
            'print' => '1',
        ]);
        $printResponse->assertOk()
            ->assertDontSee('Priority')
            ->assertDontSee($ticket->priority)
            ->assertSee('Ticket details')
            ->assertSee('Client information')
            ->assertSee('Ticket conversation')
            ->assertSee('Cannot access Bluebook')
            ->assertSee('October 27, 2026 at 6:00 PM')
            ->assertSee('The course page returns an error during an urgent outage.')
            ->assertSee('Jamie Student')
            ->assertSee('window.print()');
        $this->assertFalse($printResponse->headers->has('Content-Disposition'));
    }

    public function test_confirmed_ticket_is_routed_to_the_least_loaded_staff_member(): void
    {
        Notification::fake();
        $department = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $category = Category::factory()->create([
            'name' => 'Account access',
            'description' => 'Login and credential issues.',
            'department_id' => $department->id,
        ]);
        $categoryFromOtherDepartment = Category::factory()->create([
            'name' => 'Printer support',
            'description' => 'Printer and scanner issues.',
            'department_id' => $otherDepartment->id,
        ]);
        $busyStaff = User::factory()->create([
            'role' => 'staff',
            'department_id' => $department->id,
        ]);
        $availableStaff = User::factory()->create([
            'role' => 'staff',
            'department_id' => $department->id,
        ]);
        User::factory()->create([
            'role' => 'staff',
            'department_id' => $department->id,
            'staff_approval_status' => 'denied',
        ]);
        $otherDepartmentAdmin = User::factory()->create([
            'role' => 'department_admin',
            'department_id' => $otherDepartment->id,
        ]);

        foreach ([1, 2] as $ticketNumber) {
            Ticket::query()->create([
                'ticket_number' => sprintf('ED-2026-9000%02d', $ticketNumber),
                'department_id' => $department->id,
                'assigned_to_user_id' => $busyStaff->id,
                'requester_name' => 'Existing Requester',
                'student_id' => '2023000001',
                'requester_email' => "existing{$ticketNumber}@example.edu",
                'subject' => 'Existing work',
                'description' => 'An active ticket.',
                'priority' => 'Low',
                'status' => 'In Progress',
            ]);
        }

        $this->post(route('ticket.store'), [
            'name' => 'Jamie Student',
            'student_id' => '2023000002',
            'email' => 'jamie@example.edu',
            'category_id' => $categoryFromOtherDepartment->id,
            'subject' => 'Account access problem',
            'message' => 'I cannot log in to my account. A system-wide outage prevents online classes.',
        ])->assertRedirect(route('ticket.submitted'));

        $this->confirmPendingEmail();
        $ticket = Ticket::query()->where('requester_email', 'jamie@example.edu')->firstOrFail();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'department_id' => $department->id,
            'category_id' => $category->id,
            'assigned_to_user_id' => $availableStaff->id,
            'status' => 'Assigned',
            'priority' => 'Urgent',
        ]);
        $this->assertNotNull($ticket->assigned_at);
        $this->assertNotNull($ticket->first_assigned_at);

        $triageEvent = TicketEvent::query()
            ->where('ticket_id', $ticket->id)
            ->where('event', 'automated_triage')
            ->firstOrFail();
        $this->assertSame('assigned', $triageEvent->properties['routing_status']);
        $this->assertSame($availableStaff->id, $triageEvent->properties['assigned_to_user_id']);
        $this->assertSame('ticket_text', $triageEvent->properties['routing_method']);
        $this->assertSame($category->id, $triageEvent->properties['routed_category_id']);

        $this->assertDatabaseHas('workspace_notifications', [
            'user_id' => $availableStaff->id,
            'ticket_id' => $ticket->id,
            'type' => 'assignment',
            'title' => 'Ticket assigned to you',
        ]);
        $this->assertDatabaseHas('workspace_notifications', [
            'user_id' => $busyStaff->id,
            'ticket_id' => $ticket->id,
            'type' => 'new_ticket',
        ]);
        $this->assertDatabaseMissing('workspace_notifications', [
            'user_id' => $otherDepartmentAdmin->id,
            'ticket_id' => $ticket->id,
            'type' => 'new_ticket',
        ]);
    }

    public function test_ticket_text_automatically_routes_a_category_when_the_match_is_unambiguous(): void
    {
        Notification::fake();
        $department = Department::factory()->create();
        $category = Category::factory()->create([
            'name' => 'Student portal access',
            'description' => 'Student learning platform login.',
            'department_id' => $department->id,
        ]);
        $staff = User::factory()->create([
            'role' => 'staff',
            'department_id' => $department->id,
        ]);

        $this->post(route('ticket.store'), [
            'name' => 'Jamie Student',
            'student_id' => '2023000005',
            'email' => 'jamie@example.edu',
            'subject' => 'Student portal access problem',
            'message' => 'I cannot open the student portal to access my classes.',
        ])->assertRedirect(route('ticket.submitted'));

        $this->confirmPendingEmail();
        $ticket = Ticket::query()->where('requester_email', 'jamie@example.edu')->firstOrFail();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'category_id' => $category->id,
            'department_id' => $department->id,
            'assigned_to_user_id' => $staff->id,
            'status' => 'Assigned',
        ]);
    }

    public function test_inactive_category_matches_stay_in_the_department_review_queue(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        Category::factory()->create([
            'name' => 'Student portal access',
            'is_active' => false,
        ]);

        $this->post(route('ticket.store'), [
            'name' => 'Jamie Student',
            'student_id' => '2023000003',
            'email' => 'jamie@example.edu',
            'subject' => 'Student portal access problem',
            'message' => 'I cannot access the student portal.',
        ])->assertRedirect(route('ticket.submitted'));

        $this->confirmPendingEmail();
        $ticket = Ticket::query()->where('requester_email', 'jamie@example.edu')->firstOrFail();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'department_id' => null,
            'category_id' => null,
            'assigned_to_user_id' => null,
            'status' => 'New',
        ]);
        $triageEvent = TicketEvent::query()
            ->where('ticket_id', $ticket->id)
            ->where('event', 'automated_triage')
            ->firstOrFail();
        $this->assertSame('needs_department', $triageEvent->properties['routing_status']);

        $this->actingAs($admin)
            ->get(route('staff.tickets'))
            ->assertOk()
            ->assertSee($ticket->ticket_number)
            ->assertSee('Needs review')
            ->assertDontSee('Routing: All', false);

        $this->get(route('staff.ticket-detail', $ticket))
            ->assertOk()
            ->assertSee('could not match the ticket text to an active category route. Department review is needed.');
    }

    public function test_ambiguous_ticket_text_stays_in_the_department_review_queue(): void
    {
        Notification::fake();
        $firstDepartment = Department::factory()->create();
        $secondDepartment = Department::factory()->create();
        Category::factory()->create([
            'name' => 'Account access',
            'department_id' => $firstDepartment->id,
        ]);
        Category::factory()->create([
            'name' => 'Portal access',
            'department_id' => $secondDepartment->id,
        ]);

        $this->post(route('ticket.store'), [
            'name' => 'Jamie Student',
            'student_id' => '2023000006',
            'email' => 'jamie@example.edu',
            'subject' => 'Account, portal, and access issue',
            'message' => 'Both account and portal need access.',
        ])->assertRedirect(route('ticket.submitted'));

        $this->confirmPendingEmail();
        $ticket = Ticket::query()->where('requester_email', 'jamie@example.edu')->firstOrFail();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'department_id' => null,
            'category_id' => null,
            'assigned_to_user_id' => null,
            'status' => 'New',
        ]);
        $triageEvent = TicketEvent::query()
            ->where('ticket_id', $ticket->id)
            ->where('event', 'automated_triage')
            ->firstOrFail();
        $this->assertSame('needs_department', $triageEvent->properties['routing_status']);
    }

    public function test_ticket_with_no_eligible_department_staff_stays_unassigned_for_review(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $department = Department::factory()->create();
        $category = Category::factory()->create([
            'name' => 'Account access',
            'description' => 'Login and credential issues.',
            'department_id' => $department->id,
        ]);
        User::factory()->create([
            'role' => 'staff',
            'department_id' => $department->id,
            'staff_approval_status' => 'denied',
        ]);

        $this->post(route('ticket.store'), [
            'name' => 'Jamie Student',
            'student_id' => '2023000004',
            'email' => 'jamie@example.edu',
            'subject' => 'Account access problem',
            'message' => 'I cannot log in to my account.',
        ])->assertRedirect(route('ticket.submitted'));

        $this->confirmPendingEmail();
        $ticket = Ticket::query()->where('requester_email', 'jamie@example.edu')->firstOrFail();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'category_id' => $category->id,
            'department_id' => $department->id,
            'assigned_to_user_id' => null,
            'status' => 'New',
        ]);
        $triageEvent = TicketEvent::query()
            ->where('ticket_id', $ticket->id)
            ->where('event', 'automated_triage')
            ->firstOrFail();
        $this->assertSame('needs_staff_assignment', $triageEvent->properties['routing_status']);

        $this->actingAs($admin)
            ->get(route('staff.ticket-detail', $ticket))
            ->assertOk()
            ->assertSee('no eligible staff were available');
    }

    public function test_client_can_download_a_ticket_with_only_their_verified_public_conversation(): void
    {
        $ticket = $this->createTicket();
        $staff = User::factory()->create(['role' => 'staff']);
        $ticket->comments()->create([
            'author_id' => $staff->id,
            'author_name' => 'PCU IT Support',
            'body' => 'Visible support update.',
            'is_internal' => false,
        ]);
        $ticket->comments()->create([
            'author_id' => $staff->id,
            'author_name' => 'PCU IT Support',
            'body' => 'Internal note must not be downloaded.',
            'is_internal' => true,
        ]);

        $this->post(route('ticket.lookup'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'jamie@example.edu',
        ])->assertOk()
            ->assertDontSee('Priority')
            ->assertDontSee($ticket->priority)
            ->assertSee('Department')
            ->assertSee('Category')
            ->assertSee('Not available')
            ->assertSee($ticket->created_at->copy()->timezone('Asia/Manila')->format('F j, Y \a\t g:i A'))
            ->assertSee('name="print" value="1"', false)
            ->assertSee('Print ticket')
            ->assertSee('Download ticket');

        $downloadResponse = $this->post(route('ticket.download'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'jamie@example.edu',
        ]);
        $downloadResponse->assertOk()
            ->assertDontSee('Priority')
            ->assertDontSee($ticket->priority)
            ->assertSee('Ticket details')
            ->assertSee('Client information')
            ->assertSee('class="ticket-detail--email"', false)
            ->assertSee('href="mailto:jamie@example.edu"', false)
            ->assertSee('Visible support update.')
            ->assertDontSee('Internal note must not be downloaded.');

        $pdf = app(TicketPdf::class)->generate($ticket);
        $this->assertStringNotContainsString('Priority:', $pdf);
        $this->assertStringNotContainsString($ticket->priority, $pdf);
        $this->assertStringContainsString('TICKET DETAILS', $pdf);
        $this->assertStringContainsString('CLIENT INFORMATION', $pdf);
        $this->assertStringContainsString('Assigned to', $pdf);
        $this->assertStringContainsString('Visible support update.', $pdf);
        $this->assertStringNotContainsString('Internal note must not be downloaded.', $pdf);
        $this->assertStringContainsString('Check ticket status: '.route('ticket.check'), $pdf);

        $printResponse = $this->post(route('ticket.download'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'jamie@example.edu',
            'print' => '1',
        ]);
        $printResponse->assertOk()
            ->assertDontSee('Priority')
            ->assertDontSee($ticket->priority)
            ->assertSee('Ticket details')
            ->assertSee('Client information')
            ->assertSee('Visible support update.')
            ->assertDontSee('Internal note must not be downloaded.')
            ->assertSee('window.print()');
        $this->assertFalse($printResponse->headers->has('Content-Disposition'));

        $this->post(route('ticket.download'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'wrong@example.edu',
        ])->assertNotFound();
    }

    public function test_multi_page_ticket_pdf_repeats_the_status_footer_on_each_page(): void
    {
        $ticket = $this->createTicket();
        $staff = User::factory()->create(['role' => 'staff']);
        $ticket->comments()->create([
            'author_id' => $staff->id,
            'author_name' => $staff->name,
            'body' => str_repeat('The requester is still experiencing the issue and needs the support team to review the ticket again. ', 110),
            'is_internal' => false,
        ]);

        $pdf = app(TicketPdf::class)->generate($ticket);
        $this->assertSame(1, preg_match('/\/Count (\d+)/', $pdf, $pageCountMatch));
        $pageCount = (int) $pageCountMatch[1];

        $this->assertGreaterThan(1, $pageCount);
        $this->assertSame($pageCount, substr_count($pdf, 'Check ticket status: '.route('ticket.check')));
        $this->assertStringContainsString('TICKET DETAILS', $pdf);
        $this->assertStringContainsString('CLIENT INFORMATION', $pdf);
    }

    public function test_client_can_see_the_actual_name_of_the_staff_member_assigned_to_their_ticket(): void
    {
        $staff = User::factory()->create([
            'name' => 'Taylor Morgan',
            'nickname' => 'Tay',
            'role' => 'staff',
        ]);
        $ticket = $this->createTicket();
        $ticket->update(['assigned_to_user_id' => $staff->id]);

        $this->post(route('ticket.lookup'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'jamie@example.edu',
        ])->assertOk()
            ->assertSee('Assigned to')
            ->assertSee('Taylor Morgan')
            ->assertDontSee('>Tay<', false);

        $this->post(route('ticket.download'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'jamie@example.edu',
        ])->assertOk()
            ->assertSee('Assigned to')
            ->assertSee('Taylor Morgan')
            ->assertDontSee('>Tay<', false);
    }

    public function test_invalid_attachment_in_a_multiple_file_submission_is_reported_by_file_index(): void
    {
        Storage::fake('private');
        $department = Department::factory()->create();

        $this->from(route('ticket.create'))
            ->post(route('ticket.store'), [
                'name' => 'Jamie Student',
                'student_id' => '2023000001',
                'email' => 'jamie@example.edu',
                'department_id' => $department->id,
                'subject' => 'Cannot access Bluebook',
                'message' => 'The course page returns an error.',
                'attachment' => [
                    UploadedFile::fake()->create('proof.pdf', 50, 'application/pdf'),
                    UploadedFile::fake()->create('too-large.pdf', 5121, 'application/pdf'),
                ],
            ])
            ->assertRedirect(route('ticket.create'))
            ->assertSessionHasErrors([
                'attachment.1' => 'File 2 must not exceed 5 MB.',
            ]);

        $this->assertDatabaseCount('tickets', 0);
        Storage::disk('private')->assertDirectoryEmpty('ticket-attachments');
    }

    public function test_submission_rejects_more_than_five_attachments(): void
    {
        Storage::fake('private');
        $department = Department::factory()->create();

        $this->from(route('ticket.create'))
            ->post(route('ticket.store'), [
                'name' => 'Jamie Student',
                'student_id' => '2023000001',
                'email' => 'jamie@example.edu',
                'department_id' => $department->id,
                'subject' => 'Cannot access Bluebook',
                'message' => 'The course page returns an error.',
                'attachment' => [
                    UploadedFile::fake()->create('one.pdf', 50, 'application/pdf'),
                    UploadedFile::fake()->create('two.pdf', 50, 'application/pdf'),
                    UploadedFile::fake()->create('three.pdf', 50, 'application/pdf'),
                    UploadedFile::fake()->create('four.pdf', 50, 'application/pdf'),
                    UploadedFile::fake()->create('five.pdf', 50, 'application/pdf'),
                    UploadedFile::fake()->create('six.pdf', 50, 'application/pdf'),
                ],
            ])
            ->assertRedirect(route('ticket.create'))
            ->assertSessionHasErrors([
                'attachment' => 'You can attach up to 5 files to a ticket.',
            ]);

        $this->assertDatabaseCount('tickets', 0);
        Storage::disk('private')->assertDirectoryEmpty('ticket-attachments');
    }

    public function test_guest_submission_uses_combined_ticket_text_priority_instead_of_requester_input(): void
    {
        $department = Department::factory()->create();
        Notification::fake();
        $response = $this->post(route('ticket.store'), [
            'name' => 'Jamie Student',
            'student_id' => '2023000001',
            'email' => 'jamie@example.edu',
            'department_id' => $department->id,
            'subject' => 'Help with my class portal',
            'message' => 'A system-wide outage prevents online classes.',
            'priority' => 'Low',
        ]);

        $response->assertRedirect(route('ticket.submitted'));
        $this->confirmPendingEmail();
        $this->assertDatabaseHas('tickets', [
            'requester_email' => 'jamie@example.edu',
            'subject' => 'Help with my class portal',
            'priority' => 'Urgent',
            'department_id' => null,
            'category_id' => null,
        ]);
    }

    public function test_ticket_text_keyword_rules_assign_high_medium_and_fallback_low_priorities(): void
    {
        $department = Department::factory()->create();
        $cases = [
            ['subject' => 'Cannot sign in to the class portal', 'description' => 'Please assist.', 'priority' => 'High'],
            ['subject' => 'Slow loading in the course portal', 'description' => 'Please assist.', 'priority' => 'Medium'],
            ['subject' => 'Question about training materials', 'description' => 'Please assist.', 'priority' => 'Low'],
        ];

        foreach ($cases as $index => $case) {
            Notification::fake();
            $this->post(route('ticket.store'), [
                'name' => 'Jamie Student',
                'student_id' => '2023000001',
                'email' => "jamie{$index}@example.edu",
                'department_id' => $department->id,
                'subject' => $case['subject'],
                'message' => $case['description'],
            ])->assertRedirect(route('ticket.submitted'));
            $this->confirmPendingEmail();

            $this->assertDatabaseHas('tickets', [
                'subject' => $case['subject'],
                'priority' => $case['priority'],
            ]);
        }
    }

    public function test_priority_classification_uses_description_when_subject_has_no_priority_keywords(): void
    {
        Notification::fake();
        $this->post(route('ticket.store'), [
            'name' => 'Jamie Student',
            'student_id' => '2023000001',
            'email' => 'jamie@example.edu',
            'subject' => 'My account issue',
            'message' => 'I cannot log in because authentication failed.',
        ])->assertRedirect(route('ticket.submitted'));

        $this->confirmPendingEmail();

        $this->assertDatabaseHas('tickets', [
            'requester_email' => 'jamie@example.edu',
            'subject' => 'My account issue',
            'priority' => 'High',
        ]);
    }

    public function test_invalid_ticket_submission_does_not_create_a_ticket(): void
    {
        $response = $this->from(route('ticket.create'))->post(route('ticket.store'), []);

        $response->assertRedirect(route('ticket.create'));
        $response->assertSessionHasErrors(['name', 'student_id', 'email', 'subject', 'message']);
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_ticket_submission_requires_a_ten_digit_id_and_valid_phone_number(): void
    {
        $this->from(route('ticket.create'))
            ->post(route('ticket.store'), [
                'name' => 'Jamie Student',
                'student_id' => 'PCU-2026-001',
                'email' => 'jamie@example.edu',
                'phone' => '0917123456A',
                'subject' => 'Cannot access Bluebook',
                'message' => 'The course page returns an error.',
            ])
            ->assertRedirect(route('ticket.create'))
            ->assertSessionHasErrors([
                'student_id' => 'Student or employee ID must contain exactly 10 digits.',
                'phone' => 'Phone number must be 11 digits and start with 09.',
            ]);

        $this->assertDatabaseCount('pending_ticket_submissions', 0);
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_ticket_submission_rejects_numeric_ids_and_phone_numbers_with_wrong_lengths(): void
    {
        $this->from(route('ticket.create'))
            ->post(route('ticket.store'), [
                'name' => 'Jamie Student',
                'student_id' => '123456789',
                'email' => 'jamie@example.edu',
                'phone' => '0917123456',
                'subject' => 'Cannot access Bluebook',
                'message' => 'The course page returns an error.',
            ])
            ->assertRedirect(route('ticket.create'))
            ->assertSessionHasErrors([
                'student_id' => 'Student or employee ID must contain exactly 10 digits.',
                'phone' => 'Phone number must be 11 digits and start with 09.',
            ]);

        $this->assertDatabaseCount('pending_ticket_submissions', 0);
    }

    public function test_ticket_submission_requires_phone_number_to_start_with_zero_nine(): void
    {
        $this->from(route('ticket.create'))
            ->post(route('ticket.store'), [
                'name' => 'Jamie Student',
                'student_id' => '2023000001',
                'email' => 'jamie@example.edu',
                'phone' => '08171234567',
                'subject' => 'Cannot access Bluebook',
                'message' => 'The course page returns an error.',
            ])
            ->assertRedirect(route('ticket.create'))
            ->assertSessionHasErrors([
                'phone' => 'Phone number must be 11 digits and start with 09.',
            ]);

        $this->assertDatabaseCount('pending_ticket_submissions', 0);
    }

    public function test_blocked_client_email_cannot_submit_a_ticket_until_admin_clears_the_block(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $department = Department::factory()->create();
        $moderation = ClientModeration::create([
            'email' => 'jamie@example.edu',
            'status' => 'blocked',
            'reason' => 'Repeated spam submissions.',
            'acted_by_id' => $admin->id,
        ]);

        $this->from(route('ticket.create'))
            ->post(route('ticket.store'), [
                'name' => 'Jamie Student',
                'student_id' => '2023000001',
                'email' => 'JAMIE@example.edu',
                'department_id' => $department->id,
                'subject' => 'Another test ticket',
                'message' => 'This should not be accepted.',
            ])
            ->assertRedirect(route('ticket.create'))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('tickets', 0);

        $this->actingAs($admin)
            ->patch(route('admin.moderation.clear', $moderation))
            ->assertRedirect(route('admin.moderation'));

        Notification::fake();
        $this->post(route('ticket.store'), [
            'name' => 'Jamie Student',
            'student_id' => '2023000001',
            'email' => 'jamie@example.edu',
            'department_id' => $department->id,
            'subject' => 'Legitimate support request',
            'message' => 'I need help accessing my account.',
        ])->assertRedirect(route('ticket.submitted'));
        $this->confirmPendingEmail();

        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_database_defaults_ticket_priority_to_low(): void
    {
        $ticket = Ticket::create([
            'requester_name' => 'Jamie Student',
            'student_id' => '2023000001',
            'requester_email' => 'jamie@example.edu',
            'subject' => 'Cannot access Bluebook',
            'description' => 'The course page returns an error.',
        ]);

        $this->assertSame('Low', $ticket->refresh()->priority);
    }

    public function test_lookup_requires_matching_ticket_number_and_email(): void
    {
        $staff = User::factory()->create([
            'name' => 'Alex Individual',
            'role' => 'staff',
        ]);
        $ticket = $this->createTicket();
        $ticket->description = '<script>alert("xss")</script>';
        $ticket->save();
        $ticket->comments()->create([
            'author_id' => $staff->id,
            'author_name' => $staff->name,
            'body' => 'Requester-visible progress update.',
            'is_internal' => false,
        ]);
        $ticket->comments()->create([
            'author_id' => $staff->id,
            'author_name' => 'IT Support',
            'body' => 'Internal escalation note.',
            'is_internal' => true,
        ]);

        $this->post(route('ticket.lookup'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'wrong@example.edu',
        ])->assertOk()
            ->assertSee('No ticket matched those details.')
            ->assertDontSee($ticket->subject);

        $this->post(route('ticket.lookup'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'jamie@example.edu',
        ])->assertOk()
            ->assertSee($ticket->ticket_number)
            ->assertSee('Close ticket view')
            ->assertSee($ticket->subject)
            ->assertSee('Your support request')
            ->assertSee('Ticket details')
            ->assertSee('Client information')
            ->assertSee('class="ticket-detail--email"', false)
            ->assertSee('href="mailto:jamie@example.edu"', false)
            ->assertSee('Assigned to')
            ->assertSee('Not assigned')
            ->assertSee('Current status')
            ->assertSee('Ticket conversation')
            ->assertSee('Have more to add?')
            ->assertDontSee('Only reply if the issue is still happening or comes up again.')
            ->assertSee('Type your reply to the PCU Helpdesk...')
            ->assertDontSee('Need to check a different ticket?')
            ->assertSee('<script>alert("xss")</script>')
            ->assertDontSee('<script>alert("xss")</script>', false)
            ->assertSee('Requester-visible progress update.')
            ->assertSee('PCU IT Support')
            ->assertSee('images/Philippine_Christian_University_logo.png')
            ->assertDontSee('Alex Individual')
            ->assertDontSee('Internal escalation note.');
    }

    public function test_requester_tracker_shows_assignee_timeline_and_first_response_estimate(): void
    {
        config()->set('app.display_timezone', 'Asia/Manila');
        $this->travelTo('2026-11-02 01:00:00 UTC');
        $department = Department::factory()->create(['name' => 'PCU Systems Support']);
        $staff = User::factory()->create([
            'name' => 'Alex Support',
            'role' => 'staff',
            'department_id' => $department->id,
        ]);
        $ticket = Ticket::create([
            'ticket_number' => 'ED-2026-001234',
            'assigned_to_user_id' => $staff->id,
            'requester_name' => 'Jamie Student',
            'student_id' => '2023000001',
            'requester_email' => 'jamie@example.edu',
            'department_id' => $department->id,
            'subject' => 'Cannot access the student portal',
            'description' => 'The student portal shows an error when I sign in.',
            'priority' => 'Urgent',
            'status' => 'Assigned',
        ]);
        $ticket->events()->create([
            'event' => 'automated_triage',
            'description' => 'Private classifier details must not be shown.',
            'properties' => ['routing_status' => 'assigned'],
        ]);
        $ticket->events()->create([
            'event' => 'status_changed',
            'description' => 'Sensitive status detail must not be shown.',
            'properties' => ['new_status' => 'In Progress'],
        ]);
        $ticket->events()->create([
            'event' => 'staff_sensitive_audit',
            'description' => 'Sensitive audit detail must not be shown.',
        ]);
        $ticket->comments()->create([
            'author_id' => $staff->id,
            'author_name' => $staff->name,
            'body' => 'Internal comment body must not be shown.',
            'is_internal' => true,
        ]);

        $this->post(route('ticket.lookup'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'jamie@example.edu',
        ])->assertOk()
            ->assertSee('What happens next')
            ->assertSee('PCU Systems Support')
            ->assertSee('Alex Support')
            ->assertSee('Expected by November 2, 2026 at 10:00 AM')
            ->assertSee('Request timeline')
            ->assertSee('Request routed')
            ->assertSee('Status changed to In Progress.')
            ->assertDontSee('Private classifier details')
            ->assertDontSee('Sensitive status detail')
            ->assertDontSee('Sensitive audit detail')
            ->assertDontSee('Internal comment body');

        $ticket->comments()->create([
            'author_id' => $staff->id,
            'author_name' => $staff->name,
            'body' => 'We have replied to your request.',
            'is_internal' => false,
        ]);

    }

    public function test_requester_tracker_stops_showing_estimate_after_staff_reply(): void
    {
        config()->set('app.display_timezone', 'Asia/Manila');
        $staff = User::factory()->create(['role' => 'staff']);
        $ticket = $this->createTicket();
        $ticket->update(['assigned_to_user_id' => $staff->id]);
        $ticket->comments()->create([
            'author_id' => $staff->id,
            'author_name' => $staff->name,
            'body' => 'We have replied to your request.',
            'is_internal' => false,
        ]);

        $this->post(route('ticket.lookup'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'jamie@example.edu',
        ])->assertOk()
            ->assertSee('A support team member has replied.')
            ->assertDontSee('Expected by');
    }

    public function test_resolved_and_closed_tickets_tell_clients_to_submit_a_new_ticket(): void
    {
        $ticket = $this->createTicket();

        foreach (['Resolved', 'Closed'] as $status) {
            $ticket->update(['status' => $status]);

            $response = $this->post(route('ticket.lookup'), [
                'ticket' => $ticket->ticket_number,
                'email' => 'jamie@example.edu',
            ])->assertOk();

            $response->assertSee('This ticket is '.strtolower($status).'.')
                ->assertSee("{$status} tickets no longer accept replies or attachments.")
                ->assertSee('<a href="'.route('ticket.create').'">submit a new ticket</a>', false)
                ->assertDontSee('name="attachment[]"', false)
                ->assertDontSee('name="body"', false);
        }
    }

    public function test_attachment_download_requires_matching_requester_email(): void
    {
        Storage::fake('private');
        $ticket = $this->createTicket();
        Storage::disk('private')->put('ticket-attachments/proof.pdf', 'private contents');
        $attachment = TicketAttachment::create([
            'ticket_id' => $ticket->id,
            'original_name' => 'proof.pdf',
            'disk' => 'private',
            'path' => 'ticket-attachments/proof.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 16,
        ]);

        $this->post(route('ticket.attachments.download', $attachment), [
            'ticket' => $ticket->ticket_number,
            'email' => 'wrong@example.edu',
        ])->assertNotFound();

        $this->post(route('ticket.attachments.download', $attachment), [
            'ticket' => $ticket->ticket_number,
            'email' => 'jamie@example.edu',
        ])->assertOk();
    }

    public function test_client_can_reply_to_a_ticket_and_notifies_its_department_queue(): void
    {
        $department = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $departmentAdmin = User::factory()->create([
            'role' => 'department_admin',
            'department_id' => $department->id,
        ]);
        $otherDepartmentAdmin = User::factory()->create([
            'role' => 'department_admin',
            'department_id' => $otherDepartment->id,
        ]);
        $staff = User::factory()->create([
            'role' => 'staff',
            'department_id' => $department->id,
        ]);
        $ticket = $this->createTicket();
        $ticket->update(['department_id' => $department->id]);

        $this->post(route('ticket.lookup'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'jamie@example.edu',
        ])
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="attachment[]"', false)
            ->assertSee('Attach up to 5 files per reply.');

        $this->post(route('ticket.reply'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'JAMIE@example.edu',
            'body' => 'I still cannot sign in.',
        ])->assertRedirect(route('ticket.check'));

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'author_id' => null,
            'author_name' => 'Jamie Student',
            'body' => 'I still cannot sign in.',
            'is_internal' => false,
        ]);
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket->id,
            'event' => 'requester_reply',
        ]);
        $this->assertDatabaseHas('workspace_notifications', [
            'user_id' => $departmentAdmin->id,
            'ticket_id' => $ticket->id,
            'type' => 'requester_reply',
        ]);
        $this->assertDatabaseHas('workspace_notifications', [
            'user_id' => $otherDepartmentAdmin->id,
            'ticket_id' => $ticket->id,
            'type' => 'requester_reply',
        ]);
        $this->assertDatabaseHas('workspace_notifications', [
            'user_id' => $staff->id,
            'ticket_id' => $ticket->id,
            'type' => 'requester_reply',
        ]);

        $this->get(route('ticket.check'))
            ->assertOk()
            ->assertSee('I still cannot sign in.');
    }

    public function test_client_can_attach_multiple_files_to_a_reply_and_preview_them_in_the_conversation(): void
    {
        Storage::fake('private');
        $department = Department::factory()->create();
        $staff = User::factory()->create([
            'role' => 'staff',
            'department_id' => $department->id,
        ]);
        $ticket = $this->createTicket();
        $ticket->update(['department_id' => $department->id]);

        $this->get(route('ticket.attachments.preview', 1))->assertNotFound();

        $this->post(route('ticket.lookup'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'jamie@example.edu',
        ])->assertOk();

        $this->post(route('ticket.reply'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'jamie@example.edu',
            'body' => 'Here are the screenshots and error details.',
            'attachment' => [
                UploadedFile::fake()->create('error-screen.png', 20, 'image/png'),
                UploadedFile::fake()->create('diagnostic.pdf', 20, 'application/pdf'),
            ],
        ])->assertRedirect(route('ticket.check'));

        $comment = $ticket->comments()->with('attachments')->firstOrFail();
        $this->assertCount(2, $comment->attachments);
        foreach ($comment->attachments as $attachment) {
            Storage::disk('private')->assertExists($attachment->path);
            $this->assertSame($comment->id, $attachment->comment_id);
            $this->assertSame(basename($attachment->path), $attachment->original_name);
        }

        $image = $comment->attachments->firstWhere('mime_type', 'image/png');
        $this->assertNotNull($image);
        $this->assertNotSame('error-screen.png', $image->original_name);
        $document = $comment->attachments->firstWhere('mime_type', 'application/pdf');
        $this->assertNotNull($document);
        $this->post(route('ticket.view.close'))->assertRedirect(route('ticket.check'));
        $this->get(route('ticket.attachments.preview', $image))->assertNotFound();

        $this->post(route('ticket.lookup'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'jamie@example.edu',
        ])->assertOk();

        $this->get(route('ticket.attachments.preview', $image))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->get(route('ticket.check'))
            ->assertOk()
            ->assertSee('Here are the screenshots and error details.')
            ->assertSee($image->original_name)
            ->assertSee($document->original_name)
            ->assertDontSee('error-screen.png')
            ->assertDontSee('diagnostic.pdf')
            ->assertSee('data-image-preview', false)
            ->assertSee('data-attachment-preview-dialog', false);

        $this->actingAs($staff)
            ->get(route('staff.ticket-detail', $ticket))
            ->assertOk()
            ->assertSee($image->original_name)
            ->assertSee($document->original_name)
            ->assertDontSee('error-screen.png')
            ->assertDontSee('diagnostic.pdf');
    }

    public function test_client_reply_rejects_more_than_five_attachments(): void
    {
        Storage::fake('private');
        $ticket = $this->createTicket();

        $this->post(route('ticket.lookup'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'jamie@example.edu',
        ])->assertOk();

        $this->from(route('ticket.check'))
            ->post(route('ticket.reply'), [
                'ticket' => $ticket->ticket_number,
                'email' => 'jamie@example.edu',
                'body' => 'Uploading too many files.',
                'attachment' => array_map(
                    fn (int $index) => UploadedFile::fake()->create("screen-{$index}.png", 20, 'image/png'),
                    range(1, 6),
                ),
            ])
            ->assertRedirect(route('ticket.check'))
            ->assertSessionHasErrors('attachment');

        $this->assertDatabaseCount('ticket_comments', 0);
        Storage::disk('private')->assertDirectoryEmpty('ticket-attachments');
    }

    public function test_client_cannot_view_or_download_internal_note_attachments(): void
    {
        Storage::fake('private');
        $staff = User::factory()->create(['role' => 'staff']);
        $ticket = $this->createTicket();
        Storage::disk('private')->put('ticket-attachments/internal.png', 'private image content');
        $comment = $ticket->comments()->create([
            'author_id' => $staff->id,
            'author_name' => $staff->name,
            'body' => 'Internal review only.',
            'is_internal' => true,
        ]);
        $attachment = TicketAttachment::create([
            'ticket_id' => $ticket->id,
            'comment_id' => $comment->id,
            'uploaded_by' => $staff->id,
            'original_name' => 'internal.png',
            'disk' => 'private',
            'path' => 'ticket-attachments/internal.png',
            'mime_type' => 'image/png',
            'size_bytes' => 21,
        ]);

        $this->post(route('ticket.lookup'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'jamie@example.edu',
        ])->assertOk();

        $this->get(route('ticket.check'))
            ->assertOk()
            ->assertDontSee('Internal review only.')
            ->assertDontSee('internal.png');

        $this->get(route('ticket.attachments.preview', $attachment))->assertNotFound();
        $this->post(route('ticket.attachments.download', $attachment), [
            'ticket' => $ticket->ticket_number,
            'email' => 'jamie@example.edu',
        ])->assertNotFound();
    }

    public function test_closed_ticket_rejects_client_replies_and_attachments(): void
    {
        Storage::fake('private');
        $ticket = $this->createTicket();
        $resolvedAt = now()->subDay();
        $ticket->update([
            'status' => 'Closed',
            'resolved_at' => $resolvedAt,
        ]);

        $this->post(route('ticket.lookup'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'jamie@example.edu',
        ])->assertOk();

        $this->post(route('ticket.reply'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'jamie@example.edu',
            'body' => 'The issue has happened again.',
            'attachment' => [
                UploadedFile::fake()->create('follow-up.pdf', 20, 'application/pdf'),
            ],
        ])->assertRedirect(route('ticket.check'))
            ->assertSessionHas('ticket_reply_error', 'Resolved or closed tickets can no longer accept replies or attachments.');

        $ticket->refresh();
        $this->assertSame('Closed', $ticket->status);
        $this->assertSame($resolvedAt->toDateTimeString(), $ticket->resolved_at->toDateTimeString());
        $this->assertDatabaseCount('ticket_comments', 0);
        $this->assertDatabaseMissing('ticket_events', [
            'ticket_id' => $ticket->id,
            'event' => 'requester_reply',
        ]);
        $this->assertDatabaseMissing('workspace_notifications', [
            'ticket_id' => $ticket->id,
            'type' => 'requester_reply',
        ]);
        $this->assertDatabaseCount('ticket_attachments', 0);
        Storage::disk('private')->assertDirectoryEmpty('ticket-attachments');

        $this->get(route('ticket.check'))
            ->assertOk()
            ->assertSee('Closed tickets no longer accept replies or attachments.')
            ->assertDontSee('name="attachment[]"', false)
            ->assertDontSee('name="body"', false);
    }

    public function test_resolved_ticket_rejects_client_replies_without_reopening(): void
    {
        Storage::fake('private');
        $ticket = $this->createTicket();
        $resolvedAt = now()->subDay();
        $ticket->update([
            'status' => 'Resolved',
            'resolved_at' => $resolvedAt,
        ]);

        $this->post(route('ticket.reply'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'jamie@example.edu',
            'body' => 'The issue is still happening.',
            'attachment' => [
                UploadedFile::fake()->create('follow-up.pdf', 20, 'application/pdf'),
            ],
        ])->assertRedirect(route('ticket.check'))
            ->assertSessionHas('ticket_reply_error', 'Resolved or closed tickets can no longer accept replies or attachments.');

        $ticket->refresh();
        $this->assertSame('Resolved', $ticket->status);
        $this->assertSame($resolvedAt->toDateTimeString(), $ticket->resolved_at->toDateTimeString());
        $this->assertDatabaseCount('ticket_comments', 0);
        $this->assertDatabaseMissing('ticket_events', [
            'ticket_id' => $ticket->id,
            'event' => 'requester_reply',
        ]);
        $this->assertDatabaseMissing('workspace_notifications', [
            'ticket_id' => $ticket->id,
            'type' => 'requester_reply',
        ]);
        $this->assertDatabaseCount('ticket_attachments', 0);
        Storage::disk('private')->assertDirectoryEmpty('ticket-attachments');
    }

    public function test_client_reply_requires_the_ticket_number_and_email_to_match(): void
    {
        $ticket = $this->createTicket();

        $this->post(route('ticket.reply'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'wrong@example.edu',
            'body' => 'I still cannot sign in.',
        ])->assertRedirect(route('ticket.check'));

        $this->assertDatabaseCount('ticket_comments', 0);
        $this->assertDatabaseCount('ticket_events', 0);
        $this->assertDatabaseCount('workspace_notifications', 0);

        $this->get(route('ticket.check'))
            ->assertOk()
            ->assertSee('No ticket matched those details.');
    }

    public function test_client_reply_validation_prevents_empty_comments(): void
    {
        $ticket = $this->createTicket();
        $this->post(route('ticket.lookup'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'jamie@example.edu',
        ])->assertOk();

        $this->from(route('ticket.check'))
            ->post(route('ticket.reply'), [
                'ticket' => $ticket->ticket_number,
                'email' => 'jamie@example.edu',
                'body' => '',
            ])
            ->assertRedirect(route('ticket.check'))
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('ticket_comments', 0);
    }

    public function test_client_reply_content_is_escaped_when_displayed(): void
    {
        $ticket = $this->createTicket();
        $reply = '<script>alert("xss")</script>';

        $this->post(route('ticket.reply'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'jamie@example.edu',
            'body' => $reply,
        ])->assertRedirect(route('ticket.check'));

        $this->get(route('ticket.check'))
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee($reply, false);
    }

    public function test_client_can_close_ticket_view_to_hide_ticket_details_from_the_session(): void
    {
        $ticket = $this->createTicket();

        $this->post(route('ticket.lookup'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'jamie@example.edu',
        ])->assertOk();

        $this->post(route('ticket.view.close'))
            ->assertRedirect(route('ticket.check'));

        $this->get(route('ticket.check'))
            ->assertOk()
            ->assertSee('Ticket view closed. Your ticket details are hidden from this browser.')
            ->assertDontSee($ticket->subject)
            ->assertDontSee($ticket->ticket_number);
    }

    public function test_viewing_another_ticket_replaces_the_previous_ticket_in_the_session(): void
    {
        $firstTicket = $this->createTicket();
        $secondTicket = Ticket::create([
            'ticket_number' => 'ED-2026-000043',
            'requester_name' => 'Morgan Student',
            'student_id' => '2023000002',
            'requester_email' => 'morgan@example.edu',
            'subject' => 'Cannot connect to campus Wi-Fi',
            'description' => 'The wireless network is missing.',
            'priority' => 'Low',
            'status' => 'New',
        ]);

        $this->post(route('ticket.lookup'), [
            'ticket' => $firstTicket->ticket_number,
            'email' => 'jamie@example.edu',
        ])->assertOk();

        $this->post(route('ticket.lookup'), [
            'ticket' => $secondTicket->ticket_number,
            'email' => 'morgan@example.edu',
        ])->assertOk()
            ->assertSee($secondTicket->subject)
            ->assertDontSee($firstTicket->subject);

        $this->get(route('ticket.check'))
            ->assertOk()
            ->assertSee($secondTicket->subject)
            ->assertDontSee($firstTicket->subject);
    }

    private function createTicket(): Ticket
    {
        return Ticket::create([
            'ticket_number' => 'ED-2026-000042',
            'requester_name' => 'Jamie Student',
            'student_id' => '2023000001',
            'requester_email' => 'jamie@example.edu',
            'subject' => 'Cannot access Bluebook',
            'description' => 'The course page returns an error.',
            'priority' => 'High',
            'status' => 'In Progress',
        ]);
    }

    private function confirmPendingEmail(): void
    {
        $verificationUrl = null;

        Notification::assertSentOnDemand(
            TicketEmailVerification::class,
            function (TicketEmailVerification $notification, array $channels, AnonymousNotifiable $notifiable) use (&$verificationUrl): bool {
                $verificationUrl = $notification->verificationUrl;

                return $channels === ['mail']
                    && $notifiable->routeNotificationFor('mail') !== null;
            },
        );

        $this->assertNotNull($verificationUrl);
        $this->get($verificationUrl)->assertOk();
        $this->post($verificationUrl)->assertRedirect(route('ticket.submitted'));
    }
}
