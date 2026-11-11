<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function showAdminLogin(): View
    {
        return view('admin.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        return redirect()->intended(route('account'));
    }

    public function adminLogin(LoginRequest $request): RedirectResponse
    {
        try {
            $request->authenticate(adminOnly: true);
        } catch (ValidationException $e) {
            Log::warning('Failed admin sign-in', ['email' => $request->input('email'), 'ip' => $request->ip()]);

            throw $e;
        }

        $request->session()->put(EnsureUserIsAdmin::LAST_ACTIVITY_KEY, time());
        ActivityLog::record("Admin signed in from {$request->ip()}", $request->user());

        return redirect()->intended(route('admin.dashboard'));
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::query()->create($data);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('account'))->with('success', 'Welcome to the atelier.');
    }

    /** Change the signed-in user's password and end their sessions on other devices. */
    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $data['password']])->save();
        Auth::logoutOtherDevices($data['password']);
        $request->session()->regenerate();

        if ($user->isAdmin()) {
            ActivityLog::record('Admin password changed', $user);
        }

        return back()->with('success', 'Password updated. Other devices have been signed out.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $wasAdmin = $request->user()?->isAdmin();
        $this->signOut($request);

        return redirect()->route($wasAdmin && $request->boolean('admin') ? 'admin.login' : 'home');
    }

    private function signOut(Request $request): void
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
