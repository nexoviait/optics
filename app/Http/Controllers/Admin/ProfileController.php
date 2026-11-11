<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.profile.edit', [
            'user' => $request->user(),
            'signIns' => ActivityLog::query()->where('user_id', $request->user()->id)
                ->where('action', 'like', 'Admin signed in%')->latest()->take(8)->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            // Changing the sign-in email is sensitive, so it needs the current password.
            'current_password' => [Rule::requiredIf($request->input('email') !== $user->email), 'nullable', 'current_password'],
        ]);

        $user->fill(['name' => $data['name'], 'email' => $data['email']])->save();
        ActivityLog::record('Admin profile updated', $user);

        return back()->with('success', 'Profile saved.');
    }
}
