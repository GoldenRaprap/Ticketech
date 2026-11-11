<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\StaffPasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_has_no_header_sign_in_link_or_breadcrumb(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('<div class="container public-topbar-inner">', false)
            ->assertDontSee('Go Back')
            ->assertSee('Philippine_Christian_University_logo.png')
            ->assertDontSee('class="header-sign-in"', false)
            ->assertDontSee('class="crumb-wrap"', false)
            ->assertSee('Back to Staff Sign In');
    }

    public function test_reset_link_response_does_not_reveal_if_email_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'jamie@example.edu', 'role' => 'staff']);

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', 'If an account exists for that email, a reset link has been sent.');

        Notification::assertSentTo($user, StaffPasswordReset::class);
    }

    public function test_reset_link_uses_saved_mail_settings_and_sends_without_a_queue_worker(): void
    {
        $user = User::factory()->create(['email' => 'jamie@example.edu', 'role' => 'staff']);
        $timestamp = now();
        DB::table('system_settings')->insert([
            ['setting' => 'mail.default', 'value' => 'array', 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['setting' => 'mail.from.address', 'value' => 'helpdesk@example.edu', 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['setting' => 'mail.from.name', 'value' => 'PCU Helpdesk', 'created_at' => $timestamp, 'updated_at' => $timestamp],
        ]);
        config(['mail.default' => 'log', 'mail.default_fallback' => 'log']);
        Mail::purge('array');
        Queue::fake();

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', 'If an account exists for that email, a reset link has been sent.');

        $transport = Mail::mailer('array')->getSymfonyTransport();
        $this->assertCount(1, $transport->messages());
        $email = $transport->messages()->first()->getOriginalMessage();
        $this->assertSame('helpdesk@example.edu', $email->getFrom()[0]->getAddress());
        $this->assertSame('PCU Helpdesk', $email->getFrom()[0]->getName());
        $this->assertStringContainsString('/reset-password/', $email->getHtmlBody());
        $this->assertStringContainsString('pcu-logo@ticketech', $email->getHtmlBody());
        Queue::assertNothingPushed();
    }

    public function test_reset_link_response_is_the_same_for_unknown_email(): void
    {
        Notification::fake();

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'unknown@example.edu'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', 'If an account exists for that email, a reset link has been sent.');

        Notification::assertNothingSent();
    }

    public function test_requester_cannot_receive_a_staff_password_reset_link(): void
    {
        Notification::fake();
        $requester = User::factory()->create(['email' => 'jamie@example.edu', 'role' => 'requester']);

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $requester->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', 'If an account exists for that email, a reset link has been sent.');

        Notification::assertNothingSent();
    }

    public function test_valid_reset_token_updates_password_and_cannot_be_reused(): void
    {
        $user = User::factory()->unverified()->create(['password' => 'old-password-123', 'role' => 'staff']);
        $token = Password::createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertDontSee('Go Back')
            ->assertDontSee('class="header-sign-in"', false)
            ->assertSee('Choose a new password');

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your password has been reset. You can now sign in.');

        $this->assertTrue(Hash::check('new-password-456', $user->refresh()->password));
        $this->assertNotNull($user->email_verified_at);

        $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'another-password-789',
                'password_confirmation' => 'another-password-789',
            ])->assertRedirect(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertSessionHasErrors(['email']);

        $this->assertTrue(Hash::check('new-password-456', $user->refresh()->password));
    }
}
