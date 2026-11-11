<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Seeders\TestSeeder;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = TestSeeder::class;

    private const STATUS = 'If an account exists for that email, a reset link is on its way. It expires in 60 minutes.';

    private function customer(): User
    {
        return User::query()->where('role', User::ROLE_CUSTOMER)->firstOrFail();
    }

    public function test_sign_in_links_to_password_reset(): void
    {
        $this->get('/login')->assertOk()->assertSee(route('password.request'));
        $this->get('/forgot-password')->assertOk()->assertSee('Forgot your password?');
    }

    public function test_reset_link_is_emailed_without_revealing_which_emails_have_accounts(): void
    {
        Notification::fake();
        $customer = $this->customer();

        $this->from('/forgot-password')->post('/forgot-password', ['email' => $customer->email])
            ->assertRedirect('/forgot-password')->assertSessionHas('status', self::STATUS);
        Notification::assertSentTo($customer, ResetPassword::class);

        // Same answer for an unknown address, and nothing is sent.
        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHas('status', self::STATUS)->assertSessionHasNoErrors();
        Notification::assertSentTimes(ResetPassword::class, 1);
    }

    public function test_password_is_reset_with_the_emailed_link(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $this->post('/forgot-password', ['email' => $customer->email]);

        $token = null;
        Notification::assertSentTo($customer, ResetPassword::class, function (ResetPassword $n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->get(route('password.reset', ['token' => $token, 'email' => $customer->email]))->assertOk()->assertSee($customer->email);
        $this->post('/reset-password', ['token' => $token, 'email' => $customer->email, 'password' => 'n3w-Passw0rd', 'password_confirmation' => 'n3w-Passw0rd'])
            ->assertRedirect(route('login'))->assertSessionHas('status');

        $this->assertTrue(Hash::check('n3w-Passw0rd', $customer->fresh()->password));
        $this->post('/login', ['email' => $customer->email, 'password' => 'n3w-Passw0rd'])->assertRedirect(route('account'));
    }

    public function test_an_invalid_link_is_rejected(): void
    {
        $customer = $this->customer();
        $before = $customer->password;

        $this->from('/reset-password/bad-token')
            ->post('/reset-password', ['token' => 'bad-token', 'email' => $customer->email, 'password' => 'n3w-Passw0rd', 'password_confirmation' => 'n3w-Passw0rd'])
            ->assertSessionHasErrors('email');
        $this->assertSame($before, $customer->fresh()->password);
    }
}
