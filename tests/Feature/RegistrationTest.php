<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_registration_requires_email_verification_and_admin_approval(): void
    {
        Notification::fake();

        $this->get(route('register'))
            ->assertOk()
            ->assertDontSee('Go Back')
            ->assertSee('Register for staff access')
            ->assertSee('placeholder="e.g. Jamie Santos"', false)
            ->assertSee('placeholder="e.g. jamie.santos@pcu.edu.ph"', false)
            ->assertSee('placeholder="At least 12 characters"', false)
            ->assertSee('placeholder="Re-enter your password"', false)
            ->assertSee('aria-controls="register-password"', false)
            ->assertSee('aria-controls="register-password-confirmation"', false);

        $this->post(route('register.store'), [
            'name' => 'Jamie Staff',
            'email' => 'jamie.staff@pcu.edu.ph',
            'password' => 'secure-password-123',
            'password_confirmation' => 'secure-password-123',
            'role' => 'admin',
            'employment_status' => 'terminated',
            'staff_approval_status' => 'approved',
        ])->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'jamie.staff@pcu.edu.ph')->firstOrFail();

        $this->assertSame('staff', $user->role);
        $this->assertSame('active', $user->employment_status);
        $this->assertSame('pending', $user->staff_approval_status);
        $this->assertNull($user->email_verified_at);
        $this->assertNotSame('secure-password-123', $user->password);
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_uses_configured_smtp_when_saved_default_mailer_is_empty(): void
    {
        Notification::fake();
        DB::table('system_settings')->insert([
            [
                'setting' => 'mail.default',
                'value' => '',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'setting' => 'mail.smtp.host',
                'value' => 'smtp.example.test',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'setting' => 'mail.smtp.username',
                'value' => 'helpdesk@example.test',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'setting' => 'mail.smtp.password',
                'value' => Crypt::encryptString('configured-app-password'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->post(route('register.store'), [
            'name' => 'Jamie Staff',
            'email' => 'jamie.staff@pcu.edu.ph',
            'password' => 'secure-password-123',
            'password_confirmation' => 'secure-password-123',
        ])->assertRedirect(route('verification.notice'));

        $this->assertSame('smtp', config('mail.default'));
        $user = User::query()->where('email', 'jamie.staff@pcu.edu.ph')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_pending_staff_login_is_limited_to_verification_and_approval_status(): void
    {
        $user = $this->createPendingStaff([
            'email' => 'pending.staff@pcu.edu.ph',
            'password' => 'secure-password-123',
        ], unverified: true);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'secure-password-123',
        ])->assertRedirect(route('verification.notice'));

        $this->assertAuthenticatedAs($user);
        $this->get(route('staff.dashboard'))->assertRedirect(route('verification.notice'));
        $this->get(route('verification.notice'))
            ->assertOk()
            ->assertSee('Verify your email')
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee('class="auth-back-link verification-go-back" type="submit">Go Back</button>', false)
            ->assertDontSee('Sign out')
            ->assertDontSee('data-confirm-logout', false);

        $this->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->get(route('home'))->assertOk();
    }

    public function test_unverified_and_unapproved_staff_cannot_access_staff_routes(): void
    {
        $user = $this->createPendingStaff(unverified: true);

        $this->actingAs($user)->get(route('staff.dashboard'))
            ->assertRedirect(route('verification.notice'));

        $user->markEmailAsVerified();

        $this->get(route('staff.dashboard'))
            ->assertRedirect(route('staff.approval.pending'));
    }

    public function test_verified_staff_can_access_workspace_only_after_admin_approval(): void
    {
        $user = $this->createPendingStaff();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get(route('staff.dashboard'))
            ->assertRedirect(route('staff.approval.pending'));

        $this->actingAs($admin)->get(route('admin.users'))
            ->assertOk()
            ->assertSee('Pending staff approvals')
            ->assertSee($user->email)
            ->assertSee('Approve staff access')
            ->assertSee('Deny application');

        $this->post(route('admin.users.approve', $user))
            ->assertRedirect(route('admin.users'));

        $approvedUser = $user->fresh();
        $this->assertSame('approved', $approvedUser->staff_approval_status);
        $this->actingAs($approvedUser)->get(route('staff.dashboard'))->assertOk();
    }

    public function test_email_verification_link_marks_email_verified_and_redirects_to_approval_status(): void
    {
        $user = $this->createPendingStaff(unverified: true);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHour(),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );

        $this->actingAs($user)->get($verificationUrl)
            ->assertRedirect(route('staff.approval.pending'));

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_admin_cannot_approve_an_unverified_staff_registration(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = $this->createPendingStaff(unverified: true);

        $this->actingAs($admin)->post(route('admin.users.approve', $user))
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas('warning');

        $this->assertSame('pending', $user->fresh()->staff_approval_status);
    }

    public function test_admin_can_deny_an_unverified_staff_registration(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = $this->createPendingStaff([
            'email' => 'unverified.staff@pcu.edu.ph',
            'password' => 'secure-password-123',
        ], unverified: true);

        $this->actingAs($admin)->post(route('admin.users.deny', $user))
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas('status', $user->name.' has been denied staff access.');

        $this->assertSame('denied', $user->fresh()->staff_approval_status);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'secure-password-123',
        ])->assertRedirect(route('staff.application.denied'));

        $this->assertAuthenticatedAs($user->fresh());
        $this->get(route('staff.application.denied'))
            ->assertOk()
            ->assertSee('Your request for staff access was not approved.');
        $this->get(route('staff.dashboard'))->assertRedirect(route('staff.application.denied'));
        $this->get(route('verification.notice'))->assertRedirect(route('staff.application.denied'));
    }

    public function test_admin_can_remove_denied_unverified_application_and_release_email_for_registration(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $deniedUser = $this->createPendingStaff([
            'email' => 'unverified.staff@pcu.edu.ph',
        ], unverified: true);
        $deniedUser->staff_approval_status = 'denied';
        $deniedUser->save();

        $this->actingAs($admin)->get(route('admin.users'))
            ->assertOk()
            ->assertSee('Denied unverified applications')
            ->assertSee($deniedUser->email)
            ->assertSee('Remove application and release email')
            ->assertSee('data-confirm-delete', false);

        $this->delete(route('admin.users.denied-unverified-application.destroy', $deniedUser))
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas(
                'status',
                'The denied unverified application was removed. Its email address can now be used to register again.',
            );

        $this->assertDatabaseMissing('users', ['id' => $deniedUser->id]);
        $this->assertDatabaseHas('ticket_events', [
            'actor_id' => $admin->id,
            'event' => 'denied_unverified_staff_application_removed',
            'subject_type' => 'User',
            'subject_id' => (string) $deniedUser->id,
            'subject_name' => $deniedUser->name,
        ]);

        Auth::logout();

        $this->post(route('register.store'), [
            'name' => 'Jamie Staff Again',
            'email' => 'unverified.staff@pcu.edu.ph',
            'password' => 'secure-password-123',
            'password_confirmation' => 'secure-password-123',
        ])->assertRedirect(route('verification.notice'));

        $this->assertDatabaseHas('users', [
            'email' => 'unverified.staff@pcu.edu.ph',
            'name' => 'Jamie Staff Again',
            'staff_approval_status' => 'pending',
        ]);
    }

    public function test_only_denied_unverified_staff_applications_can_be_removed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $verifiedDeniedUser = $this->createPendingStaff();
        $verifiedDeniedUser->staff_approval_status = 'denied';
        $verifiedDeniedUser->save();
        $pendingUnverifiedUser = $this->createPendingStaff(unverified: true);

        $this->actingAs($admin)
            ->delete(route('admin.users.denied-unverified-application.destroy', $verifiedDeniedUser))
            ->assertNotFound();

        $this->delete(route('admin.users.denied-unverified-application.destroy', $pendingUnverifiedUser))
            ->assertNotFound();

        $this->assertModelExists($verifiedDeniedUser);
        $this->assertModelExists($pendingUnverifiedUser);
    }

    public function test_staff_cannot_remove_a_denied_unverified_application(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $deniedUser = $this->createPendingStaff(unverified: true);
        $deniedUser->staff_approval_status = 'denied';
        $deniedUser->save();

        $this->delete(route('admin.users.denied-unverified-application.destroy', $deniedUser))
            ->assertRedirect(route('login'));

        $this->actingAs($staff)
            ->delete(route('admin.users.denied-unverified-application.destroy', $deniedUser))
            ->assertForbidden();

        $this->assertModelExists($deniedUser);
    }

    public function test_staff_cannot_deny_another_staff_registration(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $pendingUser = $this->createPendingStaff();

        $this->actingAs($staff)->post(route('admin.users.deny', $pendingUser))
            ->assertForbidden();

        $this->assertSame('pending', $pendingUser->fresh()->staff_approval_status);
    }

    public function test_registration_rejects_mismatched_password_confirmation(): void
    {
        $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Jamie Staff',
            'email' => 'jamie.staff@pcu.edu.ph',
            'password' => 'secure-password-123',
            'password_confirmation' => 'different-password-123',
        ])->assertRedirect(route('register'))
            ->assertSessionHasErrors(['password']);

        $this->assertDatabaseCount('users', 0);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createPendingStaff(array $attributes = [], bool $unverified = false): User
    {
        $factory = $unverified ? User::factory()->unverified() : User::factory();
        $user = $factory->create([...$attributes, 'role' => 'staff']);
        $user->staff_approval_status = 'pending';
        $user->save();

        return $user;
    }
}
