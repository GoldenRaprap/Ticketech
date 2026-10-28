<?php

namespace Tests\Feature;

use App\Models\KnowledgeArticle;
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

    public function test_homepage_shows_recent_published_knowledge_articles_below_helpdesk_actions(): void
    {
        KnowledgeArticle::create([
            'title' => 'Campus Wi-Fi guide',
            'slug' => 'campus-wi-fi-guide',
            'category' => 'Network',
            'summary' => 'Connect your device to campus Wi-Fi.',
            'body' => 'Open your network settings.',
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);
        KnowledgeArticle::create([
            'title' => 'Account security guide',
            'slug' => 'account-security-guide',
            'category' => 'Accounts',
            'summary' => 'Keep your account secure.',
            'body' => 'Use a strong password.',
            'is_published' => true,
            'published_at' => now(),
        ]);
        KnowledgeArticle::create([
            'title' => 'Draft article',
            'slug' => 'draft-article',
            'category' => 'Drafts',
            'summary' => 'This article is not public.',
            'body' => 'Draft contents.',
            'is_published' => false,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('View Your Tickets')
            ->assertSee('Knowledge Base')
            ->assertSee('Account security guide')
            ->assertSee('Campus Wi-Fi guide')
            ->assertSee('Browse all articles')
            ->assertDontSee('Draft article')
            ->assertSeeInOrder([
                '<div class="help-panel">',
                '</div>',
                '<section class="home-knowledge-panel" aria-labelledby="home-knowledge-heading">',
                'Account security guide',
            ], false);
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

    public function test_public_knowledge_base_routes_redirect_staff_and_admin_to_their_workspaces(): void
    {
        $article = KnowledgeArticle::create([
            'title' => 'Campus Wi-Fi guide',
            'slug' => 'campus-wi-fi-guide',
            'category' => 'Network',
            'summary' => 'Connect a device to campus Wi-Fi.',
            'body' => 'Open your network settings.',
            'is_published' => true,
            'published_at' => now(),
        ]);
        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($staff)->get(route('knowledge-base'))
            ->assertRedirect(route('staff.dashboard'));
        $this->actingAs($staff)->get(route('knowledge-base.show', $article->slug))
            ->assertRedirect(route('staff.dashboard'));
        $this->actingAs($staff)->post(route('knowledge-base.reviews.store', $article->slug), ['rating' => 5])
            ->assertRedirect(route('staff.dashboard'));

        $this->actingAs($admin)->get(route('knowledge-base'))
            ->assertRedirect(route('admin.dashboard'));
        $this->actingAs($admin)->get(route('knowledge-base.show', $article->slug))
            ->assertRedirect(route('admin.dashboard'));
        $this->actingAs($admin)->post(route('knowledge-base.reviews.store', $article->slug), ['rating' => 5])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseCount('knowledge_article_reviews', 0);
    }
}
