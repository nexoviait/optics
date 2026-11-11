<x-layouts.admin title="Payment methods">
    <div class="space-y-6">
        <x-admin.page-head eyebrow="Finance · Payments" title="Payment methods">
            <a href="{{ route('admin.payments.index') }}" class="btn-ghost btn-sm">Back to payments</a>
            <a href="{{ route('admin.payments.methods.create') }}" class="btn btn-sm"><x-lucide-plus class="h-3.5 w-3.5" />New method</a>
        </x-admin.page-head>

        @error('methods')<p class="rounded-lg bg-destructive/10 p-3 text-xs text-destructive">{{ $message }}</p>@enderror

        <x-admin.panel title="Shown at checkout in this order">
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>#</th><th>Method</th><th>Type</th><th>On placement</th><th>Payments</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($methods as $method)
                            <tr>
                                <td class="font-mono text-xs text-muted-foreground">{{ $method->sort_order }}</td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="grid h-10 w-14 shrink-0 place-items-center rounded-lg border border-border bg-card">
                                            @if ($method->logoUrl())
                                                <img src="{{ $method->logoUrl() }}" alt="{{ $method->name }} logo" class="max-h-7 max-w-12 object-contain">
                                            @else
                                                <span class="text-[0.6rem] font-bold uppercase text-muted-foreground">{{ \Illuminate\Support\Str::limit($method->name, 4, '') }}</span>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="font-medium">{{ $method->name }}</div>
                                            <div class="font-mono text-[0.65rem] text-muted-foreground">{{ $method->code }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-xs">{{ $method->typeLabel() }}</td>
                                <td><x-admin.status :s="$method->initial_status" /></td>
                                <td class="font-mono text-xs">{{ $usage[$method->code] ?? 0 }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.payments.methods.toggle', $method) }}">
                                        @csrf @method('PATCH')
                                        <button class="flex items-center gap-2 text-xs" title="{{ $method->is_active ? 'Deactivate' : 'Activate' }} {{ $method->name }}">
                                            <span @class(['relative inline-block h-5 w-9 rounded-full transition', 'bg-primary' => $method->is_active, 'bg-border' => ! $method->is_active])>
                                                <span @class(['absolute top-0.5 h-4 w-4 rounded-full bg-card shadow transition-all', 'left-4' => $method->is_active, 'left-0.5' => ! $method->is_active])></span>
                                            </span>
                                            {{ $method->is_active ? 'Active' : 'Inactive' }}
                                        </button>
                                    </form>
                                </td>
                                <td class="whitespace-nowrap text-right">
                                    <a href="{{ route('admin.payments.methods.edit', $method) }}" class="text-xs underline">Edit</a>
                                    <form method="POST" action="{{ route('admin.payments.methods.destroy', $method) }}" class="inline" onsubmit="return confirm('Delete {{ addslashes($method->name) }}? This cannot be undone.')">@csrf @method('DELETE')<button class="ml-2 text-xs text-destructive underline">Delete</button></form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted-foreground">No payment methods yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-muted-foreground">Inactive methods are hidden from checkout. Methods with recorded payments can't be deleted — deactivate them instead so past orders keep their history.</p>
        </x-admin.panel>
    </div>
</x-layouts.admin>
