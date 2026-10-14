<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_submission_creates_a_ticket_and_private_attachment(): void
    {
        Storage::fake('private');
        $department = Department::create(['name' => 'IT Services', 'is_active' => true]);

        $response = $this->post(route('ticket.store'), [
            'name' => 'Jamie Student',
            'student_id' => 'PCU-2026-001',
            'email' => 'jamie@example.edu',
            'phone' => '09171234567',
            'department_id' => $department->id,
            'priority' => 'High',
            'subject' => 'Cannot access Bluebook',
            'message' => 'The course page returns an error.',
            'attachment' => UploadedFile::fake()->create('proof.pdf', 50, 'application/pdf'),
        ]);

        $response->assertRedirect(route('ticket.submitted'));
        $ticket = Ticket::with('attachments')->firstOrFail();
        $this->assertMatchesRegularExpression('/^ED-\d{4}-\d{6}$/', $ticket->ticket_number);
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'requester_email' => 'jamie@example.edu',
            'status' => 'New',
        ]);
        Storage::disk('private')->assertExists($ticket->attachments->firstOrFail()->path);

        $this->get(route('ticket.submitted'))->assertOk()->assertSee($ticket->ticket_number);
    }

    public function test_invalid_ticket_submission_does_not_create_a_ticket(): void
    {
        $response = $this->from(route('ticket.create'))->post(route('ticket.store'), []);

        $response->assertRedirect(route('ticket.create'));
        $response->assertSessionHasErrors(['name', 'student_id', 'email', 'department_id', 'priority', 'subject', 'message']);
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_lookup_requires_matching_ticket_number_and_email(): void
    {
        $department = Department::create(['name' => 'IT Services', 'is_active' => true]);
        $ticket = $this->createTicket($department);
        $ticket->description = '<script>alert("xss")</script>';
        $ticket->save();
        $ticket->comments()->create([
            'author_name' => 'IT Support',
            'body' => 'Requester-visible progress update.',
            'is_internal' => false,
        ]);
        $ticket->comments()->create([
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
            ->assertSee($ticket->subject)
            ->assertSee('<script>alert("xss")</script>')
            ->assertDontSee('<script>alert("xss")</script>', false)
            ->assertSee('Requester-visible progress update.')
            ->assertDontSee('Internal escalation note.');
    }

    public function test_attachment_download_requires_matching_requester_email(): void
    {
        Storage::fake('private');
        $department = Department::create(['name' => 'IT Services', 'is_active' => true]);
        $ticket = $this->createTicket($department);
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

    private function createTicket(Department $department): Ticket
    {
        return Ticket::create([
            'ticket_number' => 'ED-2026-000042',
            'department_id' => $department->id,
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
