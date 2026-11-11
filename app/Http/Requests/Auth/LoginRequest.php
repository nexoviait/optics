<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Shared credential check for the storefront and admin sign-in forms.
 * Failed attempts are throttled per email + IP so one address cannot be
 * brute-forced from a single client, independent of the route-level limit.
 */
class LoginRequest extends FormRequest
{
    public const MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Authenticate, optionally requiring the administrator role. A valid
     * customer password on the admin form fails exactly like a wrong one,
     * so the form never reveals which accounts exist or which passwords work.
     *
     * @throws ValidationException
     */
    public function authenticate(bool $adminOnly = false): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = $this->only('email', 'password');
        $remember = ! $adminOnly && $this->boolean('remember');

        $ok = $adminOnly
            ? Auth::attemptWhen($credentials, fn (User $user) => $user->isAdmin(), $remember)
            : Auth::attempt($credentials, $remember);

        if (! $ok) {
            RateLimiter::hit($this->throttleKey(), 15 * 60);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($this->throttleKey());
        $this->session()->regenerate();
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
        ]);
    }

    private function throttleKey(): string
    {
        return 'login:'.Str::transliterate(Str::lower((string) $this->input('email')).'|'.$this->ip());
    }
}
