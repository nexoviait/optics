<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $tier = $request->query('tier');

        $customers = User::query()->customers()
            ->withCount(['orders' => fn ($q) => $q->where('status', '!=', 'Cancelled')])
            ->withSum(['orders as lifetime_value' => fn ($q) => $q->where('status', '!=', 'Cancelled')], 'total')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->when(in_array($tier, config('store.tiers'), true), fn ($q) => $q->where('tier', $tier))
            ->latest()
            ->paginate(25)->withQueryString();

        $all = User::query()->customers()->withSum(['orders as lifetime_value' => fn ($q) => $q->where('status', '!=', 'Cancelled')], 'total')->get(['id', 'tier']);

        return view('admin.customers.index', [
            'customers' => $customers,
            'search' => $search,
            'tier' => $tier,
            'stats' => [
                'count' => $all->count(),
                'vip' => $all->where('tier', 'Obsidian Circle')->count(),
                'avg_ltv' => $all->count() ? $all->avg('lifetime_value') : 0,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:80'],
            'tier' => ['required', Rule::in(config('store.tiers'))],
        ]);

        // Concierge-created accounts get a random password; the client can reset it later.
        $customer = new User($data + ['password' => Str::password(16)]);
        $customer->forceFill(['role' => User::ROLE_CUSTOMER, 'tier' => $data['tier']])->save();

        return redirect()->route('admin.customers.show', $customer)->with('success', "{$customer->name} added.");
    }

    public function show(User $customer): View
    {
        abort_if($customer->isAdmin(), 404);

        $orders = $customer->orders()->with('items')->latest()->get();

        return view('admin.customers.show', [
            'customer' => $customer,
            'orders' => $orders,
            'ltv' => (float) $orders->where('status', '!=', 'Cancelled')->sum('total'),
            'prescriptions' => $customer->prescriptions()->latest()->get(),
        ]);
    }

    public function update(Request $request, User $customer): RedirectResponse
    {
        abort_if($customer->isAdmin(), 404);

        $data = $request->validate([
            'tier' => ['required', Rule::in(config('store.tiers'))],
            'phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:80'],
        ]);

        $customer->forceFill($data)->save();

        return back()->with('success', 'Client profile updated.');
    }
}
