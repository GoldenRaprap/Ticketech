<?php

namespace Tests\Feature;

use App\Models\ClientModeration;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_open_ticket_submission_and_lookup_without_signing_in(): void
    {
        $this->get(route('ticket.create'))
            ->assertOk()
            ->assertSee('No account is needed.')
            ->assertSee('Firstname Lastname')
            ->assertSee('Student or Employee ID:')
            ->assertSee('placeholder="2023******"', false)
            ->assertSee('example@pcu.edu.ph')
            ->assertSee('e.g. 09171234567')
            ->assertSee('Write like an email')
            ->assertSee('Urgent — Critical issue')
            ->assertSee('Low — General question')
            ->assertSee('value="Low" selected', false)
            ->assertSee('name="attachment[]"', false)
            ->assertSee('0</span> / 5 files selected', false)
            ->assertSee('Attach up to 5')
            ->assertSee('multiple', false)
            ->assertDontSee('name="department_id"', false)
            ->assertDontSee('name="category_id"', false);

        $this->get(route('ticket.check'))
            ->assertOk()
            ->assertSee('No account is needed.');

        $this->assertGuest();
        $this->assertSame(false, Schema::hasColumn('tickets', 'department_id'));
        $this->assertSame(false, Schema::hasColumn('tickets', 'category_id'));
    }

    public function test_guest_submission_creates_a_ticket_with_private_attachments(): void
    {
        Storage::fake('private');

        $response = $this->post(route('ticket.store'), [
            'name' => 'Jamie Student',
            'student_id' => 'PCU-2026-001',
            'email' => 'jamie@example.edu',
            'phone' => '09171234567',
            'subject' => 'Cannot access Bluebook',
            'message' => 'The course page returns an error.',
            'attachment' => [
                UploadedFile::fake()->create('proof.pdf', 50, 'application/pdf'),
                UploadedFile::fake()->create('screenshot.jpg', 50, 'image/jpeg'),
                UploadedFile::fake()->create('notes.doc', 50, 'application/msword'),
                UploadedFile::fake()->create('report.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                UploadedFile::fake()->create('image.png', 50, 'image/png'),
            ],
        ]);

        $response->assertRedirect(route('ticket.submitted'));
        $ticket = Ticket::with('attachments')->firstOrFail();
        $this->assertMatchesRegularExpression('/^ED-\d{4}-\d{6}$/', $ticket->ticket_number);
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'requester_email' => 'jamie@example.edu',
            'priority' => 'Low',
            'status' => 'New',
        ]);
        $this->assertCount(5, $ticket->attachments);
        foreach ($ticket->attachments as $attachment) {
            Storage::disk('private')->assertExists($attachment->path);
        }

        $this->get(route('ticket.submitted'))
            ->assertOk()
            ->assertSee('class="help-scene ticket-submitted-page"', false)
            ->assertSee('Ticket Submitted')
            ->assertSee($ticket->ticket_number)
            ->assertSee($ticket->created_at->format('F j, Y g:i A'))
            ->assertSee('Check Ticket Status');
    }

    public function test_invalid_attachment_in_a_multiple_file_submission_is_reported_by_file_index(): void
    {
        Storage::fake('private');

        $this->from(route('ticket.create'))
            ->post(route('ticket.store'), [
                'name' => 'Jamie Student',
                'student_id' => 'PCU-2026-001',
                'email' => 'jamie@example.edu',
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

        $this->from(route('ticket.create'))
            ->post(route('ticket.store'), [
                'name' => 'Jamie Student',
                'student_id' => 'PCU-2026-001',
                'email' => 'jamie@example.edu',
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

    public function test_guest_can_submit_a_ticket_with_urgent_priority(): void
    {
        $response = $this->post(route('ticket.store'), [
            'name' => 'Jamie Student',
            'student_id' => 'PCU-2026-001',
            'email' => 'jamie@example.edu',
            'subject' => 'Campus portal unavailable',
            'message' => 'The portal is unavailable for my class.',
            'priority' => 'Urgent',
        ]);

        $response->assertRedirect(route('ticket.submitted'));
        $this->assertDatabaseHas('tickets', [
            'requester_email' => 'jamie@example.edu',
            'subject' => 'Campus portal unavailable',
            'priority' => 'Urgent',
        ]);
    }

    public function test_invalid_ticket_submission_does_not_create_a_ticket(): void
    {
        $response = $this->from(route('ticket.create'))->post(route('ticket.store'), []);

        $response->assertRedirect(route('ticket.create'));
        $response->assertSessionHasErrors(['name', 'student_id', 'email', 'subject', 'message']);
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_blocked_client_email_cannot_submit_a_ticket_until_admin_clears_the_block(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $moderation = ClientModeration::create([
            'email' => 'jamie@example.edu',
            'status' => 'blocked',
            'reason' => 'Repeated spam submissions.',
            'acted_by_id' => $admin->id,
        ]);

        $this->from(route('ticket.create'))
            ->post(route('ticket.store'), [
                'name' => 'Jamie Student',
                'student_id' => 'PCU-2026-001',
                'email' => 'JAMIE@example.edu',
                'subject' => 'Another test ticket',
                'message' => 'This should not be accepted.',
            ])
            ->assertRedirect(route('ticket.create'))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('tickets', 0);

        $this->actingAs($admin)
            ->patch(route('admin.moderation.clear', $moderation))
            ->assertRedirect(route('admin.moderation'));

        $this->post(route('ticket.store'), [
            'name' => 'Jamie Student',
            'student_id' => 'PCU-2026-001',
            'email' => 'jamie@example.edu',
            'subject' => 'Legitimate support request',
            'message' => 'I need help accessing my account.',
        ])->assertRedirect(route('ticket.submitted'));

        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_database_defaults_ticket_priority_to_low(): void
    {
        $ticket = Ticket::create([
            'requester_name' => 'Jamie Student',
            'student_id' => 'PCU-2026-001',
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
            ->assertSee('Current status')
            ->assertSee('Ticket conversation')
            ->assertSee('Have more to add?')
            ->assertDontSee('Only reply if the issue is still happening or comes up again.')
            ->assertSee('Type your reply to the PCU Helpdesk...')
            ->assertSee('Need to check a different ticket?')
            ->assertSee('<script>alert("xss")</script>')
            ->assertDontSee('<script>alert("xss")</script>', false)
            ->assertSee('Requester-visible progress update.')
            ->assertSee('PCU IT Support')
            ->assertSee('images/Philippine_Christian_University_logo.png')
            ->assertDontSee('Alex Individual')
            ->assertDontSee('Internal escalation note.');
    }

    public function test_resolved_and_closed_tickets_warn_clients_before_replying(): void
    {
        $ticket = $this->createTicket();

        foreach (['Resolved', 'Closed'] as $status) {
            $ticket->update(['status' => $status]);

            $this->post(route('ticket.lookup'), [
                'ticket' => $ticket->ticket_number,
                'email' => 'jamie@example.edu',
            ])->assertOk()
                ->assertSee("This ticket is currently marked: {$status}.")
                ->assertSee('Only reply if the issue is still happening or comes up again.')
                ->assertSee('Your reply will reopen this ticket so PCU IT Support can assist further.');
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

    public function test_client_can_reply_to_a_verified_ticket_and_staff_are_notified(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $ticket = $this->createTicket();

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
        $staff = User::factory()->create(['role' => 'staff']);
        $ticket = $this->createTicket();

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
        }

        $image = $comment->attachments->firstWhere('original_name', 'error-screen.png');
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
            ->assertSee('error-screen.png')
            ->assertSee('diagnostic.pdf')
            ->assertSee('data-image-preview', false)
            ->assertSee('data-attachment-preview-dialog', false);

        $this->actingAs($staff)
            ->get(route('staff.ticket-detail', $ticket))
            ->assertOk()
            ->assertSee('error-screen.png')
            ->assertSee('diagnostic.pdf');
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

    public function test_client_reply_reopens_a_closed_ticket_and_clears_resolution_time(): void
    {
        $ticket = $this->createTicket();
        $ticket->update([
            'status' => 'Closed',
            'resolved_at' => now()->subDay(),
        ]);

        $this->post(route('ticket.reply'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'jamie@example.edu',
            'body' => 'The issue has happened again.',
        ])->assertRedirect(route('ticket.check'));

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'status' => 'In Progress',
            'resolved_at' => null,
        ]);
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket->id,
            'event' => 'status_changed',
            'description' => 'Ticket reopened after a requester reply (Closed → In Progress).',
        ]);
    }

    public function test_client_reply_reopens_a_resolved_ticket_and_clears_resolution_time(): void
    {
        $ticket = $this->createTicket();
        $ticket->update([
            'status' => 'Resolved',
            'resolved_at' => now()->subDay(),
        ]);

        $this->post(route('ticket.reply'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'jamie@example.edu',
            'body' => 'The issue is still happening.',
        ])->assertRedirect(route('ticket.check'));

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'status' => 'In Progress',
            'resolved_at' => null,
        ]);
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket->id,
            'event' => 'status_changed',
            'description' => 'Ticket reopened after a requester reply (Resolved → In Progress).',
        ]);
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
            'student_id' => 'PCU-2026-002',
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
            'student_id' => 'PCU-2026-001',
            'requester_email' => 'jamie@example.edu',
            'subject' => 'Cannot access Bluebook',
            'description' => 'The course page returns an error.',
            'priority' => 'High',
            'status' => 'In Progress',
        ]);
    }
}
