<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\TicketEmailVerification;
use App\Services\TurnstileVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminTurnstileSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_turnstile_keys_and_ticket_submission_uses_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        config()->set('services.turnstile.site_key', 'environment-site-key');
        config()->set('services.turnstile.secret_key', 'environment-secret-key');
        Notification::fake();

        $this->actingAs($admin)->put(route('admin.system-settings.turnstile.update'), [
            'turnstile_site_key' => 'saved-site-key',
            'turnstile_secret_key' => 'saved-secret-key',
        ])->assertRedirect(route('admin.system-settings'))
            ->assertSessionHas('status', 'Turnstile settings updated.');

        $this->assertDatabaseHas('system_settings', [
            'setting' => 'security.turnstile.site_key',
            'value' => 'saved-site-key',
        ]);
        $encryptedSecret = DB::table('system_settings')
            ->where('setting', 'security.turnstile.secret_key')
            ->value('value');
        $this->assertNotSame('saved-secret-key', $encryptedSecret);
        $this->assertSame('saved-secret-key', Crypt::decryptString($encryptedSecret));

        $this->get(route('admin.system-settings'))
            ->assertOk()
            ->assertSee('Cloudflare Turnstile')
            ->assertSee('name="turnstile_site_key"', false)
            ->assertSee('saved-site-key')
            ->assertSee('name="turnstile_secret_key"', false)
            ->assertDontSee('saved-secret-key');

        Http::preventStrayRequests();
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => true,
            ]),
        ]);

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
            && $request['secret'] === 'saved-secret-key'
            && $request['response'] === 'valid-turnstile-token');
        Notification::assertSentOnDemand(TicketEmailVerification::class);
    }

    public function test_blank_turnstile_secret_preserves_saved_value_and_admin_can_clear_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        config()->set('services.turnstile.site_key', 'environment-site-key');
        config()->set('services.turnstile.secret_key', 'environment-secret-key');
        Notification::fake();
        $encryptedSecret = Crypt::encryptString('existing-secret-key');
        DB::table('system_settings')->insert([
            [
                'setting' => 'security.turnstile.site_key',
                'value' => 'existing-site-key',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'setting' => 'security.turnstile.secret_key',
                'value' => $encryptedSecret,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($admin)->put(route('admin.system-settings.turnstile.update'), [
            'turnstile_site_key' => 'existing-site-key',
            'turnstile_secret_key' => '',
        ])->assertRedirect(route('admin.system-settings'));

        $this->assertSame(
            $encryptedSecret,
            DB::table('system_settings')
                ->where('setting', 'security.turnstile.secret_key')
                ->value('value'),
        );

        $this->put(route('admin.system-settings.turnstile.update'), [
            'turnstile_site_key' => 'existing-site-key',
            'clear_turnstile_secret_key' => '1',
        ])->assertRedirect(route('admin.system-settings'));

        $this->assertDatabaseMissing('system_settings', [
            'setting' => 'security.turnstile.secret_key',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'setting' => 'security.turnstile.site_key',
            'value' => 'existing-site-key',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => true,
            ]),
        ]);

        $this->post(route('ticket.store'), [
            'name' => 'Jamie Student',
            'student_id' => '2023000001',
            'email' => 'jamie@example.edu',
            'subject' => 'Cannot access Bluebook',
            'message' => 'The course page returns an error.',
            'cf-turnstile-response' => 'valid-turnstile-token',
        ])->assertRedirect(route('ticket.submitted'));

        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
            && $request['secret'] === 'environment-secret-key');
    }

    public function test_invalid_turnstile_secret_is_not_saved_or_flashed_to_the_session(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->from(route('admin.system-settings'))
            ->put(route('admin.system-settings.turnstile.update'), [
                'turnstile_site_key' => 'saved-site-key',
                'turnstile_secret_key' => str_repeat('x', 513),
            ])
            ->assertRedirect(route('admin.system-settings'))
            ->assertSessionHasErrors(['turnstile_secret_key'])
            ->assertSessionMissing('_old_input.turnstile_secret_key');

        $this->assertDatabaseMissing('system_settings', [
            'setting' => TurnstileVerifier::SITE_KEY_SETTING,
        ]);
        $this->assertDatabaseMissing('system_settings', [
            'setting' => TurnstileVerifier::SECRET_KEY_SETTING,
        ]);
    }

    public function test_only_admins_can_update_turnstile_settings(): void
    {
        $this->put(route('admin.system-settings.turnstile.update'), [
            'turnstile_site_key' => 'unauthorized-site-key',
            'turnstile_secret_key' => 'unauthorized-secret-key',
        ])->assertRedirect(route('login'));

        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->put(route('admin.system-settings.turnstile.update'), [
            'turnstile_site_key' => 'unauthorized-site-key',
            'turnstile_secret_key' => 'unauthorized-secret-key',
        ])->assertForbidden();

        $this->assertDatabaseMissing('system_settings', [
            'setting' => TurnstileVerifier::SITE_KEY_SETTING,
        ]);
        $this->assertDatabaseMissing('system_settings', [
            'setting' => TurnstileVerifier::SECRET_KEY_SETTING,
        ]);
    }
}
