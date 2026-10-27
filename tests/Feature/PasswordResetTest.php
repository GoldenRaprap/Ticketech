<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_shows_staff_sign_in_breadcrumb(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('<div class="container public-topbar-inner">', false)
            ->assertSee('<a href="'.route('login').'">Go Back</a>', false)
            ->assertSee('<a href="'.route('home').'">PCU Helpdesk</a>', false)
            ->assertSee('Philippine_Christian_University_logo.png')
            ->assertSee('<a class="header-sign-in" href="'.route('login').'">Sign in</a>', false)
            ->assertSee('<span aria-current="page">Forgot Password</span>', false)
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

        Notification::assertSentTo($user, ResetPassword::class);
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
        $user = User::factory()->create(['password' => 'old-password-123', 'role' => 'staff']);
        $token = Password::createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee('<a class="header-sign-in" href="'.route('login').'">Sign in</a>', false)
            ->assertSee('<a href="'.route('home').'">Go Back</a>', false)
            ->assertSee('Choose a new password');

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your password has been reset. You can now sign in.');

        $this->assertTrue(Hash::check('new-password-456', $user->refresh()->password));

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
