<form method="POST" action="{{ $zone->exists ? route('admin.settings.zones.update', $zone) : route('admin.settings.zones.store') }}" class="mt-3 grid gap-3 md:grid-cols-3">
    @csrf
    @if ($zone->exists) @method('PUT') @endif
    <x-admin.field label="Zone name" name="name"><input name="name" value="{{ $zone->name }}" required class="input"></x-admin.field>
    <x-admin.field label="Courier" name="courier"><input name="courier" value="{{ $zone->courier }}" required class="input"></x-admin.field>
    <x-admin.field label="Rate label" name="rate_label"><input name="rate_label" value="{{ $zone->rate_label }}" class="input"></x-admin.field>
    <x-admin.field label="Transit min (business days)" name="transit_min"><input type="number" min="1" name="transit_min" value="{{ $zone->transit_min }}" class="input"></x-admin.field>
    <x-admin.field label="Transit max (business days)" name="transit_max"><input type="number" min="1" name="transit_max" value="{{ $zone->transit_max }}" class="input"></x-admin.field>
    <x-admin.field label="Holidays (YYYY-MM-DD, comma separated)" name="holidays"><input name="holidays" value="{{ implode(', ', $zone->holidays ?? []) }}" class="input font-mono text-xs"></x-admin.field>
    <div class="md:col-span-3">
        <span class="field-label">Countries</span>
        <div class="flex flex-wrap gap-3 text-xs">
            @foreach ($regions as $code => $region)
                @continue($code === 'OTHER')
                <label class="flex items-center gap-1.5"><input type="checkbox" name="countries[]" value="{{ $code }}" @checked(in_array($code, $zone->countries ?? [], true))>{{ $region['name'] }}</label>
            @endforeach
        </div>
    </div>
    <label class="flex items-center gap-2 text-sm"><span class="switch"><input type="checkbox" name="enabled" value="1" @checked($zone->enabled)></span>Enabled</label>
    <div class="md:col-span-2 text-right"><button class="btn btn-sm">{{ $zone->exists ? 'Save zone' : 'Add zone' }}</button></div>
</form>
