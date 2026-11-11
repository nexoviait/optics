<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;

class PaymentMethodController extends Controller
{
    public function index(): View
    {
        return view('admin.payments.methods.index', [
            'methods' => PaymentMethod::query()->orderBy('sort_order')->orderBy('id')->get(),
            'usage' => Payment::query()->selectRaw('method, count(*) as total')->groupBy('method')->pluck('total', 'method'),
        ]);
    }

    public function create(): View
    {
        return view('admin.payments.methods.form', ['method' => new PaymentMethod([
            'type' => 'offline',
            'initial_status' => 'Pending',
            'is_active' => true,
            'sort_order' => (int) PaymentMethod::query()->max('sort_order') + 1,
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $method = PaymentMethod::query()->create($this->validated($request));
        ActivityLog::record("Payment method created: {$method->name}", $method);

        return redirect()->route('admin.payments.methods.index')->with('success', "{$method->name} added.");
    }

    public function edit(PaymentMethod $paymentMethod): View
    {
        return view('admin.payments.methods.form', ['method' => $paymentMethod]);
    }

    public function update(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        if (! $request->boolean('is_active') && $this->isLastActive($paymentMethod)) {
            return back()->withInput()->withErrors(['is_active' => 'Keep at least one checkout payment method active.']);
        }

        $paymentMethod->update($this->validated($request, $paymentMethod));
        ActivityLog::record("Payment method updated: {$paymentMethod->name}", $paymentMethod);

        return redirect()->route('admin.payments.methods.index')->with('success', "{$paymentMethod->name} saved.");
    }

    public function toggle(PaymentMethod $paymentMethod): RedirectResponse
    {
        if ($paymentMethod->is_active && $this->isLastActive($paymentMethod)) {
            return back()->withErrors(['methods' => 'Keep at least one checkout payment method active.']);
        }

        $paymentMethod->update(['is_active' => ! $paymentMethod->is_active]);
        ActivityLog::record("Payment method {$this->stateWord($paymentMethod)}: {$paymentMethod->name}", $paymentMethod);

        return back()->with('success', "{$paymentMethod->name} {$this->stateWord($paymentMethod)}.");
    }

    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        // Orders and payments keep the method code; deleting it would orphan their history.
        if (Payment::query()->where('method', $paymentMethod->code)->exists()) {
            return back()->withErrors(['methods' => "{$paymentMethod->name} has recorded payments, so it can't be deleted. Deactivate it instead."]);
        }

        if ($paymentMethod->is_active && $this->isLastActive($paymentMethod)) {
            return back()->withErrors(['methods' => 'Keep at least one checkout payment method active.']);
        }

        $this->deleteLogo($paymentMethod);
        $paymentMethod->delete();
        ActivityLog::record("Payment method deleted: {$paymentMethod->name}");

        return redirect()->route('admin.payments.methods.index')->with('success', "{$paymentMethod->name} deleted.");
    }

    private function validated(Request $request, ?PaymentMethod $method = null): array
    {
        // The code is stored on orders and payments, so it is fixed once created.
        if (! $method) {
            $request->merge(['code' => Str::slug((string) ($request->input('code') ?: $request->input('name')), '_')]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'code' => $method ? ['exclude'] : ['required', 'string', 'max:20', 'regex:/^[a-z0-9_]+$/', Rule::unique('payment_methods', 'code'), Rule::notIn(array_keys(config('store.payment_methods')))],
            'type' => ['required', Rule::in(array_keys(config('store.payment_method_types')))],
            'description' => ['nullable', 'string', 'max:160'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'account_number' => ['nullable', 'string', 'max:40'],
            'initial_status' => ['required', Rule::in(['Pending', 'Succeeded'])],
            'sort_order' => ['nullable', 'integer', 'between:0,9999'],
            // SVG is excluded on purpose: it can carry script when opened directly.
            'logo' => ['nullable', File::types(['png', 'jpg', 'jpeg', 'webp'])->max(1024), 'dimensions:min_width=16,min_height=16,max_width=1200,max_height=1200'],
            'remove_logo' => ['nullable', 'boolean'],
        ], [
            'code.unique' => 'Another payment method already uses this code.',
            'code.not_in' => 'This code is reserved for concierge payments.',
            'code.regex' => 'Use lowercase letters, numbers and underscores only.',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        if ($request->hasFile('logo')) {
            $method && $this->deleteLogo($method);
            $data['logo_path'] = $request->file('logo')->store('payment-methods', 'public');
        } elseif ($method && $request->boolean('remove_logo')) {
            $this->deleteLogo($method);
            $data['logo_path'] = null;
        }

        return collect($data)->except(['logo', 'remove_logo'])->all();
    }

    private function isLastActive(PaymentMethod $method): bool
    {
        return ! PaymentMethod::query()->where('is_active', true)->whereKeyNot($method->getKey())->exists();
    }

    private function stateWord(PaymentMethod $method): string
    {
        return $method->is_active ? 'activated' : 'deactivated';
    }

    private function deleteLogo(PaymentMethod $method): void
    {
        if ($method->logo_path && str_starts_with($method->logo_path, 'payment-methods/')) {
            Storage::disk('public')->delete($method->logo_path);
        }
    }
}
