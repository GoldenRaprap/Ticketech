<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SuperAdminSetupTest extends TestCase
{
    use RefreshDatabase;

    private const SETUP_KEY = 'test-only-super-admin-setup-key-123456789';

    public function test_fresh_install_directs_visitors_to_first_super_admin_setup(): void
    {
        config(['services.super_admin_setup_key' => self::SETUP_KEY]);

        $this->get(route('home'))->assertRedirect(route('super-admin-setup.create'));
        $this->get(route('login'))->assertRedirect(route('super-admin-setup.create'));
        $this->get(route('super-admin-setup.create'))
            ->assertOk()
            ->assertSee('Create the Super Admin account')
            ->assertSee('name="setup_key"', false);
    }

    public function test_initial_super_admin_must_verify_email_before_accessing_admin_pages(): void
    {
        config(['services.super_admin_setup_key' => self::SETUP_KEY]);
        Notification::fake();

        $this->post(route('super-admin-setup.store'), [
            'name' => 'Initial Super Admin',
            'email' => 'initial.admin@example.com',
            'password' => 'a-strong-password-123',
            'password_confirmation' => 'a-strong-password-123',
            'setup_key' => self::SETUP_KEY,
        ])->assertRedirect(route('verification.notice'));

        $admin = User::query()->where('email', 'initial.admin@example.com')->firstOrFail();

        $this->assertSame('admin', $admin->role);
        $this->assertNull($admin->email_verified_at);
        $this->assertAuthenticatedAs($admin);
        Notification::assertSentTo($admin, VerifyEmail::class);

        $this->get(route('admin.dashboard'))->assertRedirect(route('verification.notice'));

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $admin->id, 'hash' => sha1($admin->getEmailForVerification())],
        );

        $this->get($verificationUrl)->assertRedirect(route('admin.dashboard'));
        $this->assertNotNull($admin->fresh()->email_verified_at);
        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('super-admin-setup.create'))->assertRedirect(route('login'));
    }

    public function test_invalid_setup_key_does_not_create_an_account_or_flash_the_key(): void
    {
        config(['services.super_admin_setup_key' => self::SETUP_KEY]);

        $this->from(route('super-admin-setup.create'))
            ->post(route('super-admin-setup.store'), [
                'name' => 'Initial Super Admin',
                'email' => 'initial.admin@example.com',
                'password' => 'a-strong-password-123',
                'password_confirmation' => 'a-strong-password-123',
                'setup_key' => 'incorrect-setup-key',
            ])
            ->assertRedirect(route('super-admin-setup.create'))
            ->assertSessionHasErrors(['setup_key'])
            ->assertSessionMissing('_old_input.setup_key');

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_setup_is_unavailable_without_a_sufficiently_long_configured_key(): void
    {
        config(['services.super_admin_setup_key' => null]);

        $this->get(route('super-admin-setup.create'))->assertServiceUnavailable();
    }
}
