<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminMailSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_open_mail_settings(): void
    {
        $this->get(route('admin.system-settings'))->assertRedirect(route('login'));

        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get(route('admin.system-settings'))->assertForbidden();
        $this->put(route('admin.system-settings.update'), [
            'from_address' => 'attacker@example.com',
            'from_name' => 'Unauthorized Sender',
        ])->assertForbidden();

        $this->assertDatabaseCount('system_settings', 0);
    }

    public function test_admin_can_view_sender_settings_without_exposing_smtp_secrets(): void
    {
        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp.host', 'smtp.example.test');
        config()->set('mail.mailers.smtp.username', 'smtp-user@example.com');
        config()->set('mail.mailers.smtp.password', 'smtp-secret');

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.system-settings'))
            ->assertOk()
            ->assertSee('Email sender settings')
            ->assertSee('name="from_address"', false)
            ->assertSee('name="from_name"', false)
            ->assertSee('name="smtp_host"', false)
            ->assertSee('name="smtp_port"', false)
            ->assertSee('name="smtp_encryption"', false)
            ->assertSee('name="smtp_username"', false)
            ->assertSee('name="smtp_app_password"', false)
            ->assertSee('smtp.example.test')
            ->assertSee('smtp-user@example.com')
            ->assertDontSee('smtp-secret');
    }

    public function test_admin_settings_show_when_smtp_credentials_are_missing(): void
    {
        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp.username', null);
        config()->set('mail.mailers.smtp.password', null);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.system-settings'))
            ->assertOk()
            ->assertSee('Credentials needed');
    }

    public function test_admin_can_update_persistent_sender_details_used_by_later_requests(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('admin.system-settings.update'), [
            'from_address' => 'helpdesk@pcu.edu.ph',
            'from_name' => 'PCU IT Helpdesk',
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => '587',
            'smtp_encryption' => 'tls',
            'smtp_username' => 'helpdesk@pcu.edu.ph',
            'smtp_app_password' => 'smtp-app-password-secret',
        ])->assertRedirect(route('admin.system-settings'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('system_settings', [
            'setting' => 'mail.from.address',
            'value' => 'helpdesk@pcu.edu.ph',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'setting' => 'mail.from.name',
            'value' => 'PCU IT Helpdesk',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'setting' => 'mail.smtp.host',
            'value' => 'smtp.gmail.com',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'setting' => 'mail.smtp.port',
            'value' => '587',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'setting' => 'mail.smtp.username',
            'value' => 'helpdesk@pcu.edu.ph',
        ]);
        $this->assertDatabaseMissing('system_settings', [
            'setting' => 'mail.smtp.password',
            'value' => 'smtp-app-password-secret',
        ]);

        $encryptedPassword = DB::table('system_settings')
            ->where('setting', 'mail.smtp.password')
            ->value('value');
        $this->assertSame('smtp-app-password-secret', Crypt::decryptString($encryptedPassword));

        $this->get(route('admin.dashboard'))->assertOk();

        $this->assertSame('helpdesk@pcu.edu.ph', config('mail.from.address'));
        $this->assertSame('PCU IT Helpdesk', config('mail.from.name'));
        $this->assertSame('smtp.gmail.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));
        $this->assertSame('helpdesk@pcu.edu.ph', config('mail.mailers.smtp.username'));
        $this->assertSame('smtp-app-password-secret', config('mail.mailers.smtp.password'));
        $this->assertTrue(config('mail.mailers.smtp.auto_tls'));
    }

    public function test_admin_mail_settings_reject_invalid_sender_email(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->from(route('admin.system-settings'))
            ->put(route('admin.system-settings.update'), [
                'from_address' => 'not-an-email',
                'from_name' => 'PCU IT Helpdesk',
                'smtp_host' => 'smtp.gmail.com',
                'smtp_port' => '587',
                'smtp_encryption' => 'tls',
                'smtp_username' => 'helpdesk@pcu.edu.ph',
            ])
            ->assertRedirect(route('admin.system-settings'))
            ->assertSessionHasErrors(['from_address']);

        $this->assertDatabaseMissing('system_settings', [
            'setting' => 'mail.from.address',
        ]);
    }

    public function test_admin_mail_settings_reject_invalid_smtp_port_without_flashing_app_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->from(route('admin.system-settings'))
            ->put(route('admin.system-settings.update'), [
                'from_address' => 'helpdesk@pcu.edu.ph',
                'from_name' => 'PCU IT Helpdesk',
                'smtp_host' => 'smtp.gmail.com',
                'smtp_port' => '70000',
                'smtp_encryption' => 'tls',
                'smtp_username' => 'helpdesk@pcu.edu.ph',
                'smtp_app_password' => 'one-time-secret',
            ])
            ->assertRedirect(route('admin.system-settings'))
            ->assertSessionHasErrors(['smtp_port'])
            ->assertSessionMissing('_old_input.smtp_app_password');

        $this->assertDatabaseCount('system_settings', 0);
    }

    public function test_admin_can_choose_ssl_or_disable_starttls_for_smtp(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $settings = [
            'from_address' => 'helpdesk@pcu.edu.ph',
            'from_name' => 'PCU IT Helpdesk',
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => '465',
            'smtp_encryption' => 'ssl',
            'smtp_username' => 'helpdesk@pcu.edu.ph',
        ];

        $this->actingAs($admin)->put(route('admin.system-settings.update'), $settings)
            ->assertRedirect(route('admin.system-settings'));
        $this->get(route('admin.dashboard'))->assertOk();

        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertTrue(config('mail.mailers.smtp.auto_tls'));

        $this->put(route('admin.system-settings.update'), [
            ...$settings,
            'smtp_encryption' => 'none',
        ])->assertRedirect(route('admin.system-settings'));
        $this->get(route('admin.dashboard'))->assertOk();

        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));
        $this->assertFalse(config('mail.mailers.smtp.auto_tls'));
    }

    public function test_blank_app_password_preserves_the_saved_secret_and_admin_can_clear_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $encryptedPassword = Crypt::encryptString('existing-app-password');
        DB::table('system_settings')->insert([
            'setting' => 'mail.smtp.password',
            'value' => $encryptedPassword,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $settings = [
            'from_address' => 'helpdesk@pcu.edu.ph',
            'from_name' => 'PCU IT Helpdesk',
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => '587',
            'smtp_encryption' => 'tls',
            'smtp_username' => 'helpdesk@pcu.edu.ph',
        ];

        $this->actingAs($admin)->put(route('admin.system-settings.update'), $settings)
            ->assertRedirect(route('admin.system-settings'));

        $this->assertDatabaseHas('system_settings', [
            'setting' => 'mail.smtp.password',
            'value' => $encryptedPassword,
        ]);

        $this->put(route('admin.system-settings.update'), [
            ...$settings,
            'clear_smtp_app_password' => '1',
        ])->assertRedirect(route('admin.system-settings'));

        $this->assertDatabaseMissing('system_settings', [
            'setting' => 'mail.smtp.password',
        ]);
    }
}
