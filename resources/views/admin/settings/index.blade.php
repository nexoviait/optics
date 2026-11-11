@php
    $tabs = ['general' => 'General', 'branding' => 'Branding', 'region' => 'Currency & Region', 'shipping' => 'Shipping', 'sizes' => 'Size Guide', 'pricing' => 'Taxes & Pricing', 'coupons' => 'Coupons', 'checkout' => 'Checkout Safeguards', 'legal' => 'Legal & Compliance'];
    $tab = array_key_exists($tab, $tabs) ? $tab : 'general';
    $s = fn ($key, $default = '') => old($key, $settings[$key] ?? $default);
@endphp
<x-layouts.admin title="Store Settings">
    <x-admin.page-head eyebrow="Configuration" title="Store Settings" />
    <div class="flex gap-1 overflow-x-auto rounded-xl border border-border bg-card p-1">
        @foreach (array_keys($tabs) as $i => $key)
            <a href="{{ route('admin.settings.index', ['tab' => $key]) }}" @class(['whitespace-nowrap rounded-lg px-3 py-2 text-xs', 'bg-primary text-primary-foreground' => $tab === $key])><span class="mr-1 font-mono opacity-60">{{ $i + 1 }}</span>{{ $tabs[$key] }}</a>
        @endforeach
    </div>

    @if (in_array($tab, ['general', 'region', 'pricing', 'checkout', 'legal'], true))
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf @method('PUT')
            <input type="hidden" name="tab" value="{{ $tab }}">
            @if ($tab === 'general')
                <x-admin.panel title="General information & business profile">
                    <x-slot:action><button class="btn btn-sm">Save changes</button></x-slot:action>
                    <div class="grid gap-4 md:grid-cols-2">
                        @foreach (['store_name' => 'Store name', 'store_tagline' => 'Tagline', 'store_identifier' => 'Store identifier', 'primary_domain' => 'Primary domain', 'concierge_email' => 'Concierge email', 'support_phone' => 'Support phone', 'ateliers' => 'Atelier locations (footer)', 'meta_title' => 'Default SEO title'] as $key => $label)
                            <x-admin.field :label="$label" :name="$key"><input name="{{ $key }}" value="{{ $s($key) }}" class="input"></x-admin.field>
                        @endforeach
                        <x-admin.field label="Registered address" name="registered_address" class="md:col-span-2"><input name="registered_address" value="{{ $s('registered_address') }}" class="input"></x-admin.field>
                        <x-admin.field label="Footer blurb" name="footer_blurb" class="md:col-span-2"><input name="footer_blurb" value="{{ $s('footer_blurb') }}" class="input"></x-admin.field>
                        <x-admin.field label="Default SEO description" name="meta_description" class="md:col-span-2"><textarea name="meta_description" rows="2" class="input">{{ $s('meta_description') }}</textarea></x-admin.field>
                        @foreach (['social_instagram' => 'Instagram URL', 'social_facebook' => 'Facebook URL', 'social_youtube' => 'YouTube URL', 'social_x' => 'X (Twitter) URL', 'app_store_url' => 'App Store URL', 'play_store_url' => 'Google Play URL'] as $key => $label)
                            <x-admin.field :label="$label" :name="$key"><input type="url" name="{{ $key }}" value="{{ $s($key) }}" placeholder="https://" class="input"></x-admin.field>
                        @endforeach
                    </div>
                </x-admin.panel>
            @elseif ($tab === 'region')
                <x-admin.panel title="Currency & regional localization">
                    <x-slot:action><button class="btn btn-sm">Save changes</button></x-slot:action>
                    <div class="grid gap-4 md:grid-cols-3">
                        <x-admin.field label="Operating currency" name="currency"><select name="currency" class="input">@foreach (['USD' => 'USD — US Dollar', 'CHF' => 'CHF — Swiss Franc', 'EUR' => 'EUR — Euro'] as $k => $l)<option value="{{ $k }}" @selected($s('currency') === $k)>{{ $l }}</option>@endforeach</select></x-admin.field>
                        <x-admin.field label="Timezone" name="timezone"><select name="timezone" class="input">@foreach (['Europe/Zurich', 'Europe/London', 'America/New_York', 'Asia/Tokyo', 'Asia/Dhaka', 'Asia/Kolkata', 'UTC'] as $tz)<option @selected($s('timezone') === $tz)>{{ $tz }}</option>@endforeach</select></x-admin.field>
                        <x-admin.field label="Date format" name="date_format"><select name="date_format" class="input">@foreach (['d M Y', 'd.m.Y', 'm/d/Y', 'Y-m-d'] as $f)<option value="{{ $f }}" @selected($s('date_format') === $f)>{{ now()->format($f) }}</option>@endforeach</select></x-admin.field>
                    </div>
                </x-admin.panel>
            @elseif ($tab === 'pricing')
                <x-admin.panel title="Taxes & pricing rules">
                    <x-slot:action><button class="btn btn-sm">Save changes</button></x-slot:action>
                    <div class="space-y-3">
                        <x-admin.toggle name="prices_tax_inclusive" :checked="(bool) $s('prices_tax_inclusive')" label="Prices are tax-inclusive" sub="Off = duties & VAT calculated at dispatch" />
                        <x-admin.toggle name="rx_vat_exemption" :checked="(bool) $s('rx_vat_exemption')" label="Medical optical Rx VAT exemption" sub="Exempt prescription lenses where regionally permitted" />
                        <x-admin.toggle name="member_bogo_enabled" :checked="(bool) $s('member_bogo_enabled', true)" label="Member 1+1 privilege" sub="Cheapest pair free in bags of 2+ units" />
                        <div class="grid gap-3 md:grid-cols-4">
                            @foreach (['vat_ch' => 'Switzerland VAT %', 'vat_eu' => 'EU standard VAT %', 'vat_uk' => 'UK VAT %', 'free_shipping_threshold' => 'Free shipping threshold ($)'] as $key => $label)
                                <x-admin.field :label="$label" :name="$key"><input type="number" step="0.1" min="0" name="{{ $key }}" value="{{ $s($key) }}" class="input font-mono"></x-admin.field>
                            @endforeach
                        </div>
                    </div>
                </x-admin.panel>
            @elseif ($tab === 'checkout')
                <x-admin.panel title="Checkout & order safeguards">
                    <x-slot:action><button class="btn btn-sm">Save changes</button></x-slot:action>
                    <div class="space-y-3">
                        <div class="grid gap-3 md:grid-cols-4">
                            <x-admin.field label="Order numbering prefix" name="order_prefix"><input name="order_prefix" value="{{ $s('order_prefix', 'RF-') }}" class="input font-mono"></x-admin.field>
                            <x-admin.field label="Exchange window (days)" name="exchange_days"><input type="number" name="exchange_days" value="{{ $s('exchange_days', 14) }}" class="input"></x-admin.field>
                            <x-admin.field label="Warranty (days)" name="warranty_days"><input type="number" name="warranty_days" value="{{ $s('warranty_days', 365) }}" class="input"></x-admin.field>
                            <x-admin.field label="Rx submission window (days)" name="rx_submission_days"><input type="number" name="rx_submission_days" value="{{ $s('rx_submission_days', 15) }}" class="input"></x-admin.field>
                        </div>
                        <x-admin.toggle name="rx_verification_gate" :checked="(bool) $s('rx_verification_gate')" label="Mandatory optometrist verification gate" sub="Block lab dispatch until a certified optometrist approves the Rx" />
                        <x-admin.toggle name="address_validation" :checked="(bool) $s('address_validation')" label="Address validation" sub="Validate shipping addresses before payment" />
                    </div>
                </x-admin.panel>
            @elseif ($tab === 'legal')
                <x-admin.panel title="Legal policies & regulatory framework">
                    <x-slot:action><button class="btn btn-sm">Save changes</button></x-slot:action>
                    <div class="space-y-3">
                        @foreach (['policy_terms' => 'Terms of Service', 'policy_privacy' => 'Privacy & Biometric Policy', 'policy_remake' => 'Remake Guarantee (shown in bag)'] as $key => $label)
                            <x-admin.field :label="$label" :name="$key"><textarea name="{{ $key }}" rows="3" class="input">{{ $s($key) }}</textarea></x-admin.field>
                        @endforeach
                        <x-admin.toggle name="compliance_swissmedic" :checked="(bool) $s('compliance_swissmedic')" label="Swissmedic Class I medical device registration" />
                        <x-admin.toggle name="compliance_eu_mdr" :checked="(bool) $s('compliance_eu_mdr')" label="EU MDR 2017/745 compliance certification" />
                    </div>
                </x-admin.panel>
            @endif
        </form>
    @elseif ($tab === 'branding')
        <x-admin.panel title="Logo & favicon">
            <form method="POST" action="{{ route('admin.settings.branding.update') }}" enctype="multipart/form-data" class="grid gap-6 md:grid-cols-2">
                @csrf
                @foreach ([
                    'logo' => ['Logo', 'logo_path', 'PNG, JPG or WebP · square works best · max 2 MB', 'image/png,image/jpeg,image/webp'],
                    'favicon' => ['Favicon', 'favicon_path', 'PNG or ICO · 32×32 to 512×512 · max 512 KB', 'image/png,image/x-icon,.ico'],
                ] as $field => [$label, $key, $hint, $accept])
                    <div class="flex items-start gap-4 rounded-xl border border-border p-4">
                        <div class="grid h-16 w-16 shrink-0 place-items-center rounded-lg bg-secondary">
                            @if ($s($key))
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($s($key)) }}" alt="Current {{ strtolower($label) }}" class="h-12 w-12 object-contain">
                            @elseif ($field === 'logo')
                                <x-site.logo-mark class="h-12 w-12" />
                            @else
                                <img src="{{ asset('favicon.ico') }}" alt="Default favicon" class="h-8 w-8">
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <x-admin.field :label="$label" :name="$field"><input type="file" name="{{ $field }}" accept="{{ $accept }}" class="input text-xs"></x-admin.field>
                            <p class="mt-1 text-[0.7rem] text-muted-foreground">{{ $hint }}</p>
                            @if ($s($key))
                                <button type="submit" form="reset-{{ $field }}" class="mt-2 text-xs text-destructive underline" onclick="return confirm('Remove the uploaded {{ strtolower($label) }} and restore the default?')">Restore default</button>
                            @endif
                        </div>
                    </div>
                @endforeach
                <div class="text-right md:col-span-2"><button class="btn btn-sm">Upload</button></div>
            </form>
            @foreach (['logo', 'favicon'] as $field)
                <form id="reset-{{ $field }}" method="POST" action="{{ route('admin.settings.branding.destroy', $field) }}" class="hidden">@csrf @method('DELETE')</form>
            @endforeach
        </x-admin.panel>
        <x-admin.panel title="Branding & visual identity">
            <div class="grid gap-6 md:grid-cols-2">
                <div class="flex items-center gap-4"><x-site.logo-mark class="h-16 w-16" /><div class="text-sm">Emblem<p class="text-xs text-muted-foreground">Shown in the header, footer, admin and sign-in screens</p></div></div>
                <div class="text-sm">Typography stack<p class="font-display">Montserrat — Display</p><p>Manrope — Body</p><p class="font-mono text-xs">JetBrains Mono — Telemetry</p></div>
                <div class="flex flex-wrap gap-4 md:col-span-2">
                    @foreach ([['Ivory', 'var(--ivory)', '#F8F6EE'], ['Obsidian', 'var(--obsidian)', '#0F1113'], ['Sage', 'var(--sage)', '#8A9A8A'], ['Champagne Bronze', 'var(--bronze)', '#C9A887']] as [$n, $c, $h])
                        <div class="text-center text-xs"><div class="h-14 w-14 rounded-full border border-border" style="background: {{ $c }}"></div><div class="mt-1">{{ $n }}</div><div class="font-mono text-muted-foreground">{{ $h }}</div></div>
                    @endforeach
                </div>
                <p class="text-xs text-muted-foreground md:col-span-2">Brand colors are design tokens defined in <code>resources/css/app.css</code>; store name and tagline are edited under General.</p>
            </div>
        </x-admin.panel>
    @elseif ($tab === 'shipping')
        <x-admin.panel title="Shipping zones (drive storefront delivery estimates)">
            <div class="space-y-3">
                @foreach ($zones as $zone)
                    <details class="rounded-lg border border-border p-3 text-sm">
                        <summary class="flex cursor-pointer items-center justify-between gap-3"><span>{{ $zone->name }} · {{ $zone->courier }}<span class="block text-xs text-muted-foreground">{{ $zone->transit_min }}–{{ $zone->transit_max }} business days · {{ implode(' / ', $zone->countries) }}</span></span><span class="font-mono text-xs">{{ $zone->rate_label }}</span><x-admin.status :s="$zone->enabled ? 'Active' : 'Draft'" /></summary>
                        @include('admin.settings.zone-form', ['zone' => $zone])
                        <form method="POST" action="{{ route('admin.settings.zones.destroy', $zone) }}" onsubmit="return confirm('Delete this zone?')" class="mt-2 text-right">@csrf @method('DELETE')<button class="text-xs text-destructive underline">Delete zone</button></form>
                    </details>
                @endforeach
                <details class="rounded-lg border border-dashed border-border p-3 text-sm" @if ($errors->any()) open @endif>
                    <summary class="cursor-pointer font-medium">+ Add shipping zone</summary>
                    @include('admin.settings.zone-form', ['zone' => new \App\Models\ShippingZone(['enabled' => true, 'transit_min' => 2, 'transit_max' => 4, 'countries' => []])])
                </details>
            </div>
        </x-admin.panel>
    @elseif ($tab === 'sizes')
        <form method="POST" action="{{ route('admin.settings.sizes.update') }}">
            @csrf @method('PUT')
            <x-admin.panel title="Frame size guide (mm)">
                <x-slot:action><button class="btn btn-sm">Save size guide</button></x-slot:action>
                <div class="table-scroll">
                    <table class="data-table">
                        <thead><tr><th>Size</th><th>Frame width</th><th>Lens width</th><th>Bridge</th><th>Temple</th><th>Suits faces</th></tr></thead>
                        <tbody>
                            @foreach ($sizes as $size)
                                <tr>
                                    <td class="font-semibold">{{ $size->code }}</td>
                                    @foreach (['width', 'lens_width', 'bridge', 'temple'] as $field)
                                        <td><input type="number" name="sizes[{{ $size->id }}][{{ $field }}]" value="{{ $size->$field }}" class="input w-20 py-1.5 font-mono" aria-label="{{ $size->code }} {{ $field }}"></td>
                                    @endforeach
                                    <td><input name="sizes[{{ $size->id }}][face]" value="{{ $size->face }}" class="input py-1.5" aria-label="{{ $size->code }} face"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-muted-foreground">Used by the product size guide, face-width matcher and the AI Fit Advisor.</p>
            </x-admin.panel>
        </form>
    @elseif ($tab === 'coupons')
        <x-admin.panel title="Atelier codes & gift cards">
            <div class="space-y-3">
                @foreach ($coupons as $coupon)
                    <form method="POST" action="{{ route('admin.settings.coupons.update', $coupon) }}" class="grid items-end gap-2 rounded-lg border border-border p-3 sm:grid-cols-[1fr_120px_100px_160px_auto_auto]">
                        @csrf @method('PUT')
                        <x-admin.field label="Code"><input name="code" value="{{ $coupon->code }}" class="input font-mono"></x-admin.field>
                        <x-admin.field label="Type"><select name="type" class="input"><option value="percent" @selected($coupon->type === 'percent')>% off</option><option value="fixed" @selected($coupon->type === 'fixed')>$ off</option></select></x-admin.field>
                        <x-admin.field label="Value"><input type="number" step="0.01" name="value" value="{{ (float) $coupon->value }}" class="input"></x-admin.field>
                        <x-admin.field label="Expires"><input type="date" name="expires_at" value="{{ $coupon->expires_at?->toDateString() }}" class="input"></x-admin.field>
                        <label class="switch mb-2" title="Active"><input type="checkbox" name="is_active" value="1" @checked($coupon->is_active)></label>
                        <div class="flex gap-2"><button class="btn btn-sm">Save</button></div>
                    </form>
                    <form method="POST" action="{{ route('admin.settings.coupons.destroy', $coupon) }}" onsubmit="return confirm('Delete {{ $coupon->code }}?')" class="-mt-2 text-right">@csrf @method('DELETE')<button class="text-xs text-destructive underline">Delete {{ $coupon->code }}</button></form>
                @endforeach
                <form method="POST" action="{{ route('admin.settings.coupons.store') }}" class="grid items-end gap-2 rounded-lg border border-dashed border-border p-3 sm:grid-cols-[1fr_120px_100px_160px_auto_auto]">
                    @csrf
                    <x-admin.field label="New code" name="code"><input name="code" value="{{ old('code') }}" placeholder="SUMMER15" class="input font-mono"></x-admin.field>
                    <x-admin.field label="Type" name="type"><select name="type" class="input"><option value="percent">% off</option><option value="fixed">$ off</option></select></x-admin.field>
                    <x-admin.field label="Value" name="value"><input type="number" step="0.01" name="value" value="{{ old('value', 10) }}" class="input"></x-admin.field>
                    <x-admin.field label="Expires" name="expires_at"><input type="date" name="expires_at" class="input"></x-admin.field>
                    <label class="switch mb-2" title="Active"><input type="checkbox" name="is_active" value="1" checked></label>
                    <button class="btn btn-sm">Add code</button>
                </form>
            </div>
        </x-admin.panel>
    @endif
</x-layouts.admin>
