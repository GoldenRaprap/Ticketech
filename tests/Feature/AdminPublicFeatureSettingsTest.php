<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\KnowledgeArticle;
use App\Models\Ticket;
use App\Models\User;
use App\Services\PublicFeatureAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPublicFeatureSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_disable_public_knowledge_base_and_set_its_message(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $article = KnowledgeArticle::create([
            'title' => 'Campus Wi-Fi guide',
            'slug' => 'campus-wi-fi-guide',
            'category' => 'Network',
            'summary' => 'Connect to campus Wi-Fi.',
            'body' => 'Open your network settings.',
            'is_published' => true,
            'published_at' => now(),
        ]);
        $message = 'The knowledge base is unavailable for scheduled updates.';

        $this->actingAs($admin)
            ->put(route('admin.system-settings.knowledge-base.update'), [
                'knowledge_base_enabled' => '0',
                'knowledge_base_message' => $message,
            ])
            ->assertRedirect(route('admin.system-settings'))
            ->assertSessionHas('status', 'Knowledge base is disabled.');

        $this->assertDatabaseHas('system_settings', [
            'setting' => PublicFeatureAvailability::KNOWLEDGE_BASE_ENABLED,
            'value' => '0',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'setting' => PublicFeatureAvailability::KNOWLEDGE_BASE_MESSAGE,
            'value' => $message,
        ]);

        $this->get(route('admin.knowledge-base'))->assertOk();
        $this->get(route('admin.system-settings'))
            ->assertOk()
            ->assertSee('aria-label="Enable knowledge base" data-public-feature-toggle', false)
            ->assertSee('data-disable-title="Disable the knowledge base?"', false)
            ->assertSee('data-confirm-logout', false)
            ->assertSee('Public knowledge-base access is paused.')
            ->assertSee($message);

        auth()->logout();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee($message)
            ->assertDontSee('Browse all articles');
        $this->get(route('knowledge-base'))
            ->assertServiceUnavailable()
            ->assertSee($message);
        $this->get(route('knowledge-base.show', $article->slug))
            ->assertServiceUnavailable()
            ->assertSee($message);
        $this->post(route('knowledge-base.reviews.store', $article->slug), ['rating' => 5])
            ->assertServiceUnavailable();

        $this->assertDatabaseCount('knowledge_article_reviews', 0);
    }

    public function test_admin_can_disable_requester_replies_with_a_message_and_keep_ticket_lookup(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $department = Department::factory()->create();
        $ticket = Ticket::create([
            'ticket_number' => 'ED-2026-009991',
            'department_id' => $department->id,
            'requester_name' => 'Jamie Student',
            'student_id' => 'PCU-009991',
            'requester_email' => 'jamie@example.edu',
            'subject' => 'Cannot access the portal',
            'description' => 'The portal does not load.',
            'status' => 'New',
        ]);
        $message = 'Replies are paused while the helpdesk updates its systems.';

        $this->actingAs($admin)
            ->put(route('admin.system-settings.ticket-replies.update'), [
                'ticket_replies_enabled' => '0',
                'ticket_replies_message' => $message,
            ])
            ->assertRedirect(route('admin.system-settings'))
            ->assertSessionHas('status', 'Ticket replies is disabled.');

        $this->assertDatabaseHas('system_settings', [
            'setting' => PublicFeatureAvailability::TICKET_REPLIES_ENABLED,
            'value' => '0',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'setting' => PublicFeatureAvailability::TICKET_REPLIES_MESSAGE,
            'value' => $message,
        ]);

        $this->get(route('admin.system-settings'))
            ->assertOk()
            ->assertSee('aria-label="Enable ticket replies"', false)
            ->assertSee('data-disable-title="Disable ticket replies?"', false)
            ->assertSee('Requester replies are temporarily paused.')
            ->assertSee($message);

        auth()->logout();

        $this->post(route('ticket.lookup'), [
            'ticket' => $ticket->ticket_number,
            'email' => $ticket->requester_email,
        ])
            ->assertOk()
            ->assertSee($message)
            ->assertDontSee('name="body"', false);

        $this->post(route('ticket.reply'), [
            'ticket' => $ticket->ticket_number,
            'email' => $ticket->requester_email,
            'body' => 'I have another question.',
        ])->assertRedirect(route('ticket.check'));

        $this->assertDatabaseCount('ticket_comments', 0);
        $this->get(route('ticket.check'))
            ->assertOk()
            ->assertSee($ticket->ticket_number)
            ->assertSee($message)
            ->assertDontSee('name="body"', false);
    }

    public function test_non_admin_cannot_change_public_feature_availability(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->put(route('admin.system-settings.knowledge-base.update'), [
                'knowledge_base_enabled' => '0',
                'knowledge_base_message' => 'Unavailable.',
            ])
            ->assertForbidden();

        $this->put(route('admin.system-settings.ticket-replies.update'), [
            'ticket_replies_enabled' => '0',
            'ticket_replies_message' => 'Unavailable.',
        ])->assertForbidden();

        $this->assertDatabaseCount('system_settings', 0);
    }

    public function test_disabling_a_public_feature_requires_a_customer_message(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->from(route('admin.system-settings'))
            ->put(route('admin.system-settings.knowledge-base.update'), [
                'knowledge_base_enabled' => '0',
                'knowledge_base_message' => '',
            ])
            ->assertRedirect(route('admin.system-settings'))
            ->assertSessionHasErrors(['knowledge_base_message']);

        $this->assertDatabaseCount('system_settings', 0);
    }
}
