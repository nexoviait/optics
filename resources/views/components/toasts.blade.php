{{-- Toast stack fed by session flashes, validation errors and window.toast(). --}}
@php
    $flash = collect();
    if (session('success')) $flash->push(['message' => session('success'), 'type' => 'success']);
    foreach ($errors->all() as $error) $flash->push(['message' => $error, 'type' => 'error']);
@endphp
<div x-data x-init="@js($flash->values()).forEach(t => $store.toasts.push(t.message, t.type))"
     class="pointer-events-none fixed bottom-4 right-4 z-[100] flex w-[min(92vw,22rem)] flex-col gap-2" aria-live="polite">
    <template x-for="t in $store.toasts.items" :key="t.id">
        <div x-transition.opacity class="pointer-events-auto flex items-start gap-3 rounded-xl border bg-card px-4 py-3 text-sm shadow-lg"
             :class="t.type === 'error' ? 'border-destructive/50' : 'border-border'">
            <span class="mt-1 h-2 w-2 shrink-0 rounded-full" :class="t.type === 'error' ? 'bg-destructive' : 'bg-bronze'"></span>
            <p class="flex-1" x-text="t.message"></p>
            <button type="button" @click="$store.toasts.dismiss(t.id)" aria-label="Dismiss"><x-lucide-x class="h-3.5 w-3.5" /></button>
        </div>
    </template>
</div>
