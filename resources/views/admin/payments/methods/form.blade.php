@php($editing = $method->exists)
<x-layouts.admin :title="$editing ? $method->name : 'New payment method'">
    <form method="POST" action="{{ $editing ? route('admin.payments.methods.update', $method) : route('admin.payments.methods.store') }}" enctype="multipart/form-data" class="space-y-6"
          x-data="{ type: @js(old('type', $method->type)) }">
        @csrf
        @if ($editing) @method('PUT') @endif
        <x-admin.page-head eyebrow="Finance · Payment methods" :title="$editing ? $method->name : 'New payment method'">
            <a href="{{ route('admin.payments.methods.index') }}" class="btn-ghost btn-sm">Back</a>
            <button class="btn btn-sm">{{ $editing ? 'Save method' : 'Create method' }}</button>
        </x-admin.page-head>
        @error('methods')<p class="rounded-lg bg-destructive/10 p-3 text-xs text-destructive">{{ $message }}</p>@enderror
        <div class="grid gap-6 xl:grid-cols-[1.6fr_1fr]">
            <x-admin.panel title="Details">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-admin.field label="Name (shown to customers)" name="name"><input name="name" value="{{ old('name', $method->name) }}" required maxlength="60" class="input"></x-admin.field>
                    @if ($editing)
                        <x-admin.field label="Code (fixed — stored on orders)"><input value="{{ $method->code }}" disabled class="input font-mono"></x-admin.field>
                    @else
                        <x-admin.field label="Code (auto from name if empty)" name="code"><input name="code" value="{{ old('code') }}" maxlength="20" pattern="[a-z0-9_]+" data-pattern-message="Use lowercase letters, numbers and underscores only." placeholder="e.g. rocket" class="input font-mono"></x-admin.field>
                    @endif
                    <x-admin.field label="Type" name="type" class="md:col-span-2">
                        <select name="type" x-model="type" class="input">@foreach (config('store.payment_method_types') as $key => $label)<option value="{{ $key }}" @selected(old('type', $method->type) === $key)>{{ $label }}</option>@endforeach</select>
                    </x-admin.field>
                    <x-admin.field label="Short description (under the name at checkout)" name="description" class="md:col-span-2"><input name="description" value="{{ old('description', $method->description) }}" maxlength="160" class="input" placeholder="e.g. Pay with your bKash account"></x-admin.field>
                    <x-admin.field label="Merchant / receiving account number" name="account_number" class="md:col-span-2" x-show="type === 'mobile' || type === 'offline'"><input name="account_number" value="{{ old('account_number', $method->account_number) }}" maxlength="40" class="input font-mono" placeholder="e.g. 01XXXXXXXXX"></x-admin.field>
                    <x-admin.field label="Customer instructions (shown when selected)" name="instructions" class="md:col-span-2"><textarea name="instructions" rows="4" maxlength="1000" class="input">{{ old('instructions', $method->instructions) }}</textarea></x-admin.field>
                </div>
                <p class="mt-3 text-xs text-muted-foreground" x-show="type === 'mobile'">Customers must enter their wallet number and transaction ID. Both are saved on the payment so you can verify it before marking it as received.</p>
                <p class="mt-3 text-xs text-muted-foreground" x-show="type === 'card'" x-cloak>Customers enter card details, which are checked for completeness but never stored.</p>
            </x-admin.panel>
            <div class="space-y-6">
                <x-admin.panel title="Logo" class="h-fit">
                    <div class="flex items-start gap-4">
                        <div class="grid h-16 w-20 shrink-0 place-items-center rounded-lg border border-border bg-card">
                            @if ($method->logoUrl())
                                <img src="{{ $method->logoUrl() }}" alt="Current logo" class="max-h-12 max-w-16 object-contain">
                            @else
                                <x-lucide-credit-card class="h-6 w-6 text-muted-foreground" />
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <x-admin.field label="Upload logo" name="logo"><input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="input text-xs"></x-admin.field>
                            <p class="mt-1 text-[0.7rem] text-muted-foreground">PNG, JPG or WebP · transparent background works best · max 1 MB</p>
                            @if ($method->logo_path)
                                <label class="mt-2 flex items-center gap-2 text-xs text-destructive"><input type="checkbox" name="remove_logo" value="1">Remove current logo</label>
                            @endif
                        </div>
                    </div>
                </x-admin.panel>
                <x-admin.panel title="Availability" class="h-fit">
                    <div class="space-y-4">
                        <x-admin.toggle name="is_active" :checked="(bool) old('is_active', $method->is_active)" label="Active" sub="Inactive methods are hidden from checkout" />
                        @error('is_active')<p class="text-xs text-destructive">{{ $message }}</p>@enderror
                        <x-admin.field label="Payment status when an order is placed" name="initial_status">
                            <select name="initial_status" class="input">
                                <option value="Pending" @selected(old('initial_status', $method->initial_status) === 'Pending')>Pending — confirm manually</option>
                                <option value="Succeeded" @selected(old('initial_status', $method->initial_status) === 'Succeeded')>Succeeded — paid immediately</option>
                            </select>
                        </x-admin.field>
                        <x-admin.field label="Sort order (lowest first)" name="sort_order"><input type="number" min="0" max="9999" name="sort_order" value="{{ old('sort_order', $method->sort_order ?? 0) }}" class="input"></x-admin.field>
                    </div>
                </x-admin.panel>
            </div>
        </div>
    </form>
    @if ($editing)
        <form method="POST" action="{{ route('admin.payments.methods.destroy', $method) }}" onsubmit="return confirm('Delete this payment method?')" class="flex justify-end">@csrf @method('DELETE')<button class="text-xs text-destructive underline">Delete method</button></form>
    @endif
</x-layouts.admin>
