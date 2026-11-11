<x-layouts.admin title="My account">
    <x-admin.page-head eyebrow="Security" title="My account" />

    <div class="grid gap-6 lg:grid-cols-2">
        <x-admin.panel title="Profile">
            <form method="POST" action="{{ route('admin.profile.update') }}" class="space-y-3">
                @csrf
                @method('PUT')
                <x-admin.field label="Name" name="name"><input name="name" value="{{ old('name', $user->name) }}" required maxlength="80" autocomplete="name" class="input"></x-admin.field>
                <x-admin.field label="Email" name="email"><input type="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="username" class="input"></x-admin.field>
                <x-admin.field label="Current password (required to change email)" name="current_password"><input type="password" name="current_password" autocomplete="current-password" class="input"></x-admin.field>
                <div class="text-right"><button class="btn btn-sm">Save profile</button></div>
            </form>
        </x-admin.panel>

        <x-admin.panel title="Change password">
            <form method="POST" action="{{ route('password.update') }}" class="space-y-3">
                @csrf
                @method('PUT')
                <x-admin.field label="Current password">
                    <input type="password" name="current_password" required autocomplete="current-password" class="input">
                    @error('current_password', 'password')<span data-error role="alert" class="mt-1 block text-[0.7rem] text-destructive">{{ $message }}</span>@enderror
                </x-admin.field>
                <x-admin.field label="New password">
                    <input type="password" name="password" required autocomplete="new-password" class="input">
                    @error('password', 'password')<span data-error role="alert" class="mt-1 block text-[0.7rem] text-destructive">{{ $message }}</span>@enderror
                </x-admin.field>
                <x-admin.field label="Confirm new password"><input type="password" name="password_confirmation" required autocomplete="new-password" class="input"></x-admin.field>
                <p class="text-[0.7rem] text-muted-foreground">Changing your password signs you out on every other device.</p>
                <div class="text-right"><button class="btn btn-sm">Update password</button></div>
            </form>
        </x-admin.panel>
    </div>

    <x-admin.panel title="Recent sign-ins">
        <ul class="divide-y divide-border text-sm">
            @forelse ($signIns as $log)
                <li class="flex justify-between py-2"><span>{{ $log->action }}</span><span class="text-xs text-muted-foreground">{{ $log->created_at->format('j M Y, H:i') }}</span></li>
            @empty
                <li class="py-2 text-muted-foreground">No sign-ins recorded yet.</li>
            @endforelse
        </ul>
    </x-admin.panel>
</x-layouts.admin>
