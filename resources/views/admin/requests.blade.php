<x-layouts.admin title="Requests & Leads">
    <x-admin.page-head eyebrow="Concierge" title="Requests & Leads" />
    <div class="grid gap-6 xl:grid-cols-[1.6fr_1fr]">
        <x-admin.panel title="Consultations & home trials">
            <x-slot:action>
                <div class="flex rounded-full bg-secondary p-1 text-xs">
                    @foreach (['' => 'All'] + \App\Models\ServiceRequest::TYPES as $key => $label)
                        <a href="{{ route('admin.requests.index', array_filter(['type' => $key ?: null])) }}" @class(['rounded-full px-3 py-1', 'bg-primary text-primary-foreground' => ($type ?? '') === $key])>{{ $label }}</a>
                    @endforeach
                </div>
            </x-slot:action>
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>Type</th><th>Client</th><th>Details</th><th>Received</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($requests as $request)
                            <tr>
                                <td class="text-xs">{{ $request->typeLabel() }}</td>
                                <td>{{ $request->name ?? '—' }}<div class="text-xs text-muted-foreground">{{ $request->email }}</div></td>
                                <td class="text-xs">{{ $request->preferred_date ? 'Preferred '.$request->preferred_date->format('d M Y') : $request->notes }}</td>
                                <td class="text-muted-foreground">{{ $request->created_at->format('d M Y') }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.requests.update', $request) }}">
                                        @csrf @method('PATCH')
                                        <select name="status" onchange="this.form.submit()" class="input py-1 text-xs" aria-label="Status">@foreach (\App\Models\ServiceRequest::STATUSES as $s)<option @selected($request->status === $s)>{{ $s }}</option>@endforeach</select>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted-foreground">No requests yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $requests->links() }}
        </x-admin.panel>
        <x-admin.panel :title="'Newsletter subscribers ('.$subscriberCount.')'">
            <ul class="space-y-2 text-sm">
                @forelse ($subscribers as $subscriber)
                    <li class="flex justify-between border-b border-border pb-2"><span>{{ $subscriber->email }}</span><span class="text-xs text-muted-foreground">{{ $subscriber->created_at->format('d M Y') }}</span></li>
                @empty
                    <li class="text-muted-foreground">No subscribers yet.</li>
                @endforelse
            </ul>
        </x-admin.panel>
    </div>
</x-layouts.admin>
