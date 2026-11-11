<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('account.show', [
            'user' => $user,
            'orders' => $user->orders()->with('items')->latest()->get(),
            'prescriptions' => $user->prescriptions()->latest()->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'regex:/^[+\d\s-]{7,18}$/'],
            'city' => ['nullable', 'string', 'max:80'],
        ]);

        $request->user()->update($data);

        return back()->with('success', 'Profile saved.');
    }
}
