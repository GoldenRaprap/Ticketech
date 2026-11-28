<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Department;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeArticleSuggestion;
use App\Models\Ticket;
use App\Models\User;
use App\Services\KnowledgeArticleSuggestionGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeArticleAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_generate_a_new_draft_suggestion_from_similar_resolved_tickets(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $department = Department::factory()->create(['name' => 'EdTech Support']);
        $category = Category::factory()->create([
            'name' => 'Learning Management System',
            'department_id' => $department->id,
        ]);
        $staff = User::factory()->create([
            'role' => 'staff',
            'department_id' => $department->id,
        ]);

        foreach ([1, 2] as $number) {
            $ticket = Ticket::create([
                'ticket_number' => "ED-2026-00900{$number}",
                'department_id' => $department->id,
                'category_id' => $category->id,
                'requester_name' => "Resolved Requester {$number}",
                'student_id' => "202300000{$number}",
                'requester_email' => "resolved{$number}@example.edu",
                'subject' => 'LMS assignment submission receipt missing',
                'description' => 'The learning platform does not show the submission receipt.',
                'priority' => 'High',
                'status' => 'Resolved',
            ]);
            $ticket->comments()->create([
                'author_id' => $staff->id,
                'author_name' => $staff->name,
                'body' => 'Synchronize the course record and refresh the submission page.',
                'is_internal' => false,
            ]);
        }

        $this->actingAs($admin)
            ->post(route('admin.knowledge-base.generate-suggestions'))
            ->assertRedirect(route('admin.knowledge-base'))
            ->assertSessionHas('status');

        $suggestion = KnowledgeArticleSuggestion::query()->where('status', 'pending')->firstOrFail();

        $this->assertNull($suggestion->target_article_id);
        $this->assertStringContainsString('<h2>Resolution steps</h2>', $suggestion->proposed_body);
        $this->assertCount(2, $suggestion->source_ticket_ids);

        $this->actingAs($admin)
            ->post(route('admin.knowledge-base.suggestions.approve', $suggestion))
            ->assertRedirect(route('admin.knowledge-base'));

        $this->assertDatabaseHas('knowledge_article_suggestions', [
            'id' => $suggestion->id,
            'status' => 'approved',
        ]);
        $draft = KnowledgeArticle::query()->where('is_published', false)->firstOrFail();
        $this->assertSame($suggestion->title, $draft->title);
    }

    public function test_staff_cannot_approve_or_generate_article_suggestions(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $suggestion = KnowledgeArticleSuggestion::create([
            'title' => 'Printer setup',
            'category' => 'Printing',
            'summary' => 'Troubleshooting guidance.',
            'proposed_body' => '<h2>Resolution steps</h2><ol><li>Reconnect the printer.</li></ol>',
            'source_ticket_ids' => [1],
            'shared_terms' => ['printer'],
            'similarity_score' => 50,
            'status' => 'pending',
            'fingerprint' => str_repeat('a', 64),
        ]);

        $this->actingAs($staff)
            ->post(route('admin.knowledge-base.generate-suggestions'))
            ->assertForbidden();
        $this->actingAs($staff)
            ->post(route('admin.knowledge-base.suggestions.approve', $suggestion))
            ->assertForbidden();

        $suggestion->refresh();
        $this->assertSame('pending', $suggestion->status);
    }

    public function test_existing_article_update_is_saved_as_a_draft_until_staff_updates_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $department = Department::factory()->create();
        $category = Category::factory()->create([
            'name' => 'Network',
            'department_id' => $department->id,
        ]);
        $article = KnowledgeArticle::create([
            'author_id' => $admin->id,
            'title' => 'Campus Wi-Fi troubleshooting',
            'slug' => 'campus-wifi-troubleshooting',
            'category' => 'Network',
            'summary' => 'Fix common Wi-Fi connection problems.',
            'body' => '<h2>Overview</h2><p>Connect to the campus wireless network.</p>',
            'is_published' => true,
            'published_at' => now(),
        ]);
        $ticket = Ticket::create([
            'ticket_number' => 'ED-2026-009010',
            'department_id' => $department->id,
            'category_id' => $category->id,
            'requester_name' => 'Requester',
            'student_id' => '202300010',
            'requester_email' => 'requester@example.edu',
            'subject' => 'Campus Wi-Fi connection problem',
            'description' => 'My campus Wi-Fi will not connect.',
            'priority' => 'Medium',
            'status' => 'Resolved',
        ]);

        $ticket->comments()->create([
            'author_id' => $admin->id,
            'author_name' => $admin->name,
            'body' => 'Forget the old network profile and reconnect.',
            'is_internal' => false,
        ]);

        app(KnowledgeArticleSuggestionGenerator::class)->generateForTicket($ticket);
        $suggestion = KnowledgeArticleSuggestion::query()
            ->where('target_article_id', $article->id)
            ->firstOrFail();

        $this->assertStringContainsString('Campus Wi-Fi connection problem', $suggestion->summary);
        $this->assertStringContainsString('Forget the old network profile and reconnect.', $suggestion->proposed_body);
        $this->assertStringContainsString('Campus Wi-Fi connection problem', $suggestion->proposed_body);

        $this->actingAs($admin)
            ->post(route('admin.knowledge-base.suggestions.approve', $suggestion))
            ->assertRedirect(route('admin.knowledge-base'));

        $article->refresh();
        $this->assertTrue($article->is_published);
        $this->assertStringContainsString('<h2>Overview</h2>', $article->body);
        $this->assertStringNotContainsString('Additional concerns', $article->body);
        $this->assertStringContainsString('New information from resolved ticket', $article->draft_body);
        $this->assertStringContainsString('Forget the old network profile and reconnect.', $article->draft_body);
    }

    public function test_new_ticket_resolution_details_accumulate_in_the_existing_article_draft(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $department = Department::factory()->create();
        $category = Category::factory()->create([
            'name' => 'Network',
            'department_id' => $department->id,
        ]);
        $publishedBody = '<h2>Overview</h2><p>Connect to the campus wireless network.</p>';
        $article = KnowledgeArticle::create([
            'author_id' => $admin->id,
            'title' => 'Campus Wi-Fi troubleshooting',
            'slug' => 'campus-wifi-troubleshooting',
            'category' => 'Network',
            'summary' => 'Fix common Wi-Fi connection problems.',
            'body' => $publishedBody,
            'is_published' => true,
            'published_at' => now(),
        ]);
        $ticket = Ticket::create([
            'ticket_number' => 'ED-2026-009011',
            'department_id' => $department->id,
            'category_id' => $category->id,
            'requester_name' => 'Requester',
            'student_id' => '202300011',
            'requester_email' => 'requester2@example.edu',
            'subject' => 'Campus Wi-Fi connection problem',
            'description' => 'My campus Wi-Fi will not connect.',
            'priority' => 'Medium',
            'status' => 'Resolved',
        ]);
        $ticket->comments()->create([
            'author_id' => $admin->id,
            'author_name' => $admin->name,
            'body' => 'Forget the saved network profile and reconnect.',
            'is_internal' => false,
        ]);

        $generator = app(KnowledgeArticleSuggestionGenerator::class);
        $generator->generateForTicket($ticket);
        $firstSuggestion = KnowledgeArticleSuggestion::query()
            ->where('target_article_id', $article->id)
            ->firstOrFail();

        $ticket->update([
            'description' => 'My campus Wi-Fi will not connect today.',
        ]);
        $ticket->refresh();
        $generator->generateForTicket($ticket);
        $descriptionSuggestion = KnowledgeArticleSuggestion::query()
            ->where('target_article_id', $article->id)
            ->where('id', '>', $firstSuggestion->id)
            ->firstOrFail();

        $ticket->comments()->create([
            'author_id' => $admin->id,
            'author_name' => $admin->name,
            'body' => 'Renew the network lease from the device settings.',
            'is_internal' => false,
        ]);
        $ticket->refresh();
        $generator->generateForTicket($ticket);
        $suggestions = KnowledgeArticleSuggestion::query()
            ->where('target_article_id', $article->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $suggestions);
        $this->assertStringContainsString('My campus Wi-Fi will not connect today.', $descriptionSuggestion->proposed_body);
        $this->assertStringNotContainsString('Forget the saved network profile and reconnect.', $descriptionSuggestion->proposed_body);
        $this->assertStringContainsString('Renew the network lease from the device settings.', $suggestions[2]->proposed_body);
        $this->assertStringNotContainsString('Forget the saved network profile and reconnect.', $suggestions[2]->proposed_body);
        $this->assertNotSame($firstSuggestion->fingerprint, $suggestions[2]->fingerprint);

        foreach ($suggestions as $suggestion) {
            $this->actingAs($admin)
                ->post(route('admin.knowledge-base.suggestions.approve', $suggestion))
                ->assertRedirect(route('admin.knowledge-base'));
        }

        $article->refresh();
        $this->assertSame($publishedBody, $article->body);
        $this->assertSame('Fix common Wi-Fi connection problems.', $article->draft_summary);
        $this->assertStringContainsString('Forget the saved network profile and reconnect.', $article->draft_body);
        $this->assertStringContainsString('Renew the network lease from the device settings.', $article->draft_body);
        $this->assertStringContainsString('My campus Wi-Fi will not connect today.', $article->draft_body);
        $this->assertSame(1, substr_count($article->draft_body, 'Forget the saved network profile and reconnect.'));
        $this->assertDatabaseHas('knowledge_article_suggestions', [
            'id' => $firstSuggestion->id,
            'status' => 'approved',
        ]);
    }

    public function test_ticket_resolution_generates_article_suggestions_only_on_the_initial_resolution(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $department = Department::factory()->create();
        $category = Category::factory()->create([
            'department_id' => $department->id,
        ]);
        $ticket = Ticket::create([
            'ticket_number' => 'ED-2026-009012',
            'department_id' => $department->id,
            'category_id' => $category->id,
            'requester_name' => 'Requester',
            'student_id' => '202300012',
            'requester_email' => 'requester3@example.edu',
            'subject' => 'Portal access issue',
            'description' => 'The portal does not load.',
            'priority' => 'Medium',
            'status' => 'New',
        ]);

        $this->mock(KnowledgeArticleSuggestionGenerator::class)
            ->shouldReceive('generateForTicket')
            ->once()
            ->andReturnNull();

        $this->actingAs($admin)
            ->put(route('staff.ticket-update', $ticket), [
                'status' => 'Resolved',
                'priority' => 'Medium',
            ])
            ->assertRedirect(route('staff.ticket-detail', $ticket));

        $this->actingAs($admin)
            ->put(route('staff.ticket-update', $ticket), [
                'status' => 'Resolved',
                'priority' => 'Medium',
            ])
            ->assertRedirect(route('staff.ticket-detail', $ticket));

        $this->assertSame('Resolved', $ticket->fresh()->status);
    }
}
