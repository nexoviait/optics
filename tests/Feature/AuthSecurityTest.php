<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create(['email' => 'boss@example.com']);
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();

        return $admin;
    }

    public function test_admin_login_gives_customers_the_same_error_as_a_wrong_password(): void
    {
        $customer = User::factory()->create();

        $asCustomer = $this->post('/admin/login', ['email' => $customer->email, 'password' => 'password']);
        $wrong = $this->post('/admin/login', ['email' => $customer->email, 'password' => 'nope']);

        $this->assertGuest();
        $asCustomer->assertSessionHasErrors(['email' => __('auth.failed')]);
        $wrong->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    public function test_admin_login_records_activity_and_redirects(): void
    {
        $admin = $this->admin();

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'action' => 'Admin signed in from 127.0.0.1']);
    }

    public function test_login_is_locked_out_after_repeated_failures(): void
    {
        $admin = $this->admin();

        foreach (range(1, 5) as $_) {
            $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong']);
        }

        // Even the correct password is refused while locked out.
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));
    }

    public function test_idle_admin_session_expires(): void
    {
        $this->actingAs($this->admin())
            ->withSession([EnsureUserIsAdmin::LAST_ACTIVITY_KEY => time() - 31 * 60])
            ->get('/admin')
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    public function test_admin_pages_are_not_cacheable_and_send_security_headers(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/profile')->assertOk();

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_password_change_requires_current_password(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->put('/password', ['current_password' => 'wrong', 'password' => 'new-pass-123', 'password_confirmation' => 'new-pass-123'])
            ->assertSessionHasErrorsIn('password', 'current_password');

        $this->put('/password', ['current_password' => 'password', 'password' => 'new-pass-123', 'password_confirmation' => 'new-pass-123'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-pass-123', $admin->fresh()->password));
    }

    public function test_changing_admin_email_requires_current_password(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->put('/admin/profile', ['name' => 'Boss', 'email' => 'new@example.com'])->assertSessionHasErrors('current_password');
        $this->put('/admin/profile', ['name' => 'Boss', 'email' => 'new@example.com', 'current_password' => 'password'])->assertSessionHasNoErrors();

        $this->assertSame('new@example.com', $admin->fresh()->email);
    }

    public function test_reseeding_does_not_reset_the_admin_password(): void
    {
        $this->seed();
        $admin = User::query()->where('role', User::ROLE_ADMIN)->firstOrFail();
        $admin->forceFill(['password' => 'rotated-pass-1'])->save();

        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(Hash::check('rotated-pass-1', $admin->fresh()->password));
    }
}
