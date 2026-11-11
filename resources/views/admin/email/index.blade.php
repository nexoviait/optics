<x-layouts.admin title="Email & Notifications">
    <x-admin.page-head eyebrow="Communications" title="Email & Notifications">
        <a href="{{ route('admin.email.create') }}" class="btn btn-sm"><x-lucide-plus class="h-3.5 w-3.5" />New template</a>
    </x-admin.page-head>
    <p class="rounded-lg bg-bronze-soft p-3 text-xs">Templates are stored and versioned here. Delivery uses the mailer configured in <code>.env</code> (currently <code>{{ config('mail.default') }}</code>); configure SMTP or a provider to send live email.</p>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-admin.kpi label="Templates" :value="$templates->count()" />
        <x-admin.kpi label="Live" :value="$counts['live']" />
        <x-admin.kpi label="Drafts" :value="$counts['draft']" />
    </div>
    <div class="grid gap-6 xl:grid-cols-[1.2fr_1fr]">
        <x-admin.panel title="Email template library">
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>Template</th><th>Trigger hook</th><th>Class</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($templates as $template)
                            <tr @class(['bg-secondary' => $selected?->is($template)])>
                                <td class="font-medium"><a href="{{ route('admin.email.index', ['preview' => $template->slug]) }}">{{ $template->name }}</a></td>
                                <td class="font-mono text-xs">{{ $template->hook }}</td>
                                <td class="text-xs">{{ $template->category }}</td>
                                <td><x-admin.status :s="$template->status" /></td>
                                <td><a href="{{ route('admin.email.edit', $template) }}" class="text-xs underline">Edit</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted-foreground">No templates yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>
        <div x-data="{ device: 'desktop' }">
            <x-admin.panel title="Inspector & simulator">
                <x-slot:action>
                    <div class="flex rounded-full bg-secondary p-1">
                        <button type="button" @click="device = 'desktop'" :class="device === 'desktop' && 'bg-primary text-primary-foreground'" class="rounded-full p-1.5" aria-label="Desktop"><x-lucide-monitor class="h-3.5 w-3.5" /></button>
                        <button type="button" @click="device = 'mobile'" :class="device === 'mobile' && 'bg-primary text-primary-foreground'" class="rounded-full p-1.5" aria-label="Mobile"><x-lucide-smartphone class="h-3.5 w-3.5" /></button>
                    </div>
                </x-slot:action>
                @if ($selected)
                    <div :class="device === 'mobile' ? 'max-w-[300px]' : 'max-w-full'" class="mx-auto transition-all">
                        @include('admin.email.preview', ['subject' => $selected->subject, 'blocks' => $selected->blocks ?? []])
                    </div>
                @endif
                <div class="mt-3 flex flex-wrap gap-1">@foreach (config('store.email_tokens') as $token)<span class="rounded bg-secondary px-1.5 py-0.5 font-mono text-[0.6rem]">{{ $token }}</span>@endforeach</div>
            </x-admin.panel>
        </div>
    </div>
    <x-admin.panel title="Notification routing">
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>Event</th><th>Template</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach ($templates as $template)
                        <tr><td class="font-mono text-xs">{{ $template->hook }}</td><td>{{ $template->name }}</td><td><x-admin.status :s="$template->status" /></td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-admin.panel>
</x-layouts.admin>
