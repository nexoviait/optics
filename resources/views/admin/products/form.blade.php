@php($editing = $product->exists)
<x-layouts.admin :title="$editing ? $product->name : 'Add New Product'">
    <form method="POST" action="{{ $editing ? route('admin.products.update', $product) : route('admin.products.store') }}" enctype="multipart/form-data" class="space-y-6"
          x-data="{ name: @js(old('name', $product->name)), price: {{ (float) old('price', $product->price) }}, cost: {{ (float) old('cost', $product->cost) }}, shape: @js(old('shape', $product->shape)), material: @js(old('material', $product->material)),
                    finishes: @js(array_values(old('finishes', $product->finishColors()))), preview: @js($editing ? $product->imageUrl() : null) }">
        @csrf
        @if ($editing) @method('PUT') @endif
        <x-admin.page-head :eyebrow="$editing ? $product->sku : 'Products'" :title="$editing ? $product->name : 'Add New Product'">
            <a href="{{ route('admin.products.index') }}" class="btn-ghost btn-sm">Back</a>
            @if ($editing)<a href="{{ route('products.show', $product) }}" target="_blank" class="btn-ghost btn-sm">View in store ↗</a>@endif
            <button class="btn btn-sm">{{ $editing ? 'Save changes' : 'Publish product' }}</button>
        </x-admin.page-head>

        @if ($editing)
            <div class="grid gap-4 sm:grid-cols-4">
                <x-admin.kpi label="Units sold" :value="$unitsSold" />
                <x-admin.kpi label="Revenue" :value="'$'.number_format($revenue / 1000, 1).'k'" />
                <x-admin.kpi label="Page views" :value="number_format($product->views)" />
                <x-admin.kpi label="Conversion" :value="$product->views ? number_format($unitsSold / $product->views * 100, 1).'%' : '—'" />
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[1.6fr_1fr]">
            <div class="space-y-6">
                <x-admin.panel title="General information">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-admin.field label="Product name" name="name"><input name="name" x-model="name" required maxlength="120" class="input"></x-admin.field>
                        <x-admin.field label="URL slug (auto from name if empty)" name="slug"><input name="slug" value="{{ old('slug', $product->slug) }}" class="input font-mono"></x-admin.field>
                        <x-admin.field label="SKU" name="sku"><input name="sku" value="{{ old('sku', $product->sku) }}" required class="input font-mono"></x-admin.field>
                        <x-admin.field label="Category" name="category">
                            <select name="category" class="input">@foreach (config('store.categories') as $key => $label)<option value="{{ $key }}" @selected(old('category', $product->category) === $key)>{{ $label }}</option>@endforeach</select>
                        </x-admin.field>
                        <x-admin.field label="Brand" name="brand"><input name="brand" value="{{ old('brand', $product->brand) }}" class="input"></x-admin.field>
                        <x-admin.field label="Model no." name="model_no"><input name="model_no" value="{{ old('model_no', $product->model_no) }}" class="input font-mono"></x-admin.field>
                        <x-admin.field label="Gender" name="gender">
                            <select name="gender" class="input">@foreach (config('store.genders') as $key => $label)<option value="{{ $key }}" @selected(old('gender', $product->gender) === $key)>{{ $label }}</option>@endforeach</select>
                        </x-admin.field>
                        <x-admin.field label="Age group (optional, e.g. 8-12 yrs)" name="age_group"><input name="age_group" value="{{ old('age_group', $product->age_group) }}" class="input"></x-admin.field>
                        <x-admin.field label="Series / collection" name="series"><input name="series" value="{{ old('series', $product->series) }}" class="input"></x-admin.field>
                        <x-admin.field label="Badge" name="badge"><input name="badge" value="{{ old('badge', $product->badge) }}" class="input"></x-admin.field>
                        <x-admin.field label="Description" name="description" class="sm:col-span-2"><textarea name="description" rows="3" class="input">{{ old('description', $product->description) }}</textarea></x-admin.field>
                    </div>
                </x-admin.panel>

                <x-admin.panel title="Media">
                    <label class="flex cursor-pointer flex-col items-center rounded-xl border-2 border-dashed border-border p-8 text-sm text-muted-foreground">
                        <x-lucide-upload class="mb-2 h-5 w-5" />Click to upload product image (max 4 MB)
                        <input type="file" name="image" accept="image/*" class="hidden" @change="const f = $event.target.files[0]; if (f) preview = URL.createObjectURL(f)">
                    </label>
                    @error('image')<p class="mt-2 text-xs text-destructive">{{ $message }}</p>@enderror

                    <div class="mt-6 border-t border-border pt-5" x-data="{ tryOn: @js($product->try_on_image ? asset('storage/'.$product->try_on_image) : null), remove: false }">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-medium">AR try-on image <span class="font-normal text-muted-foreground">(optional)</span></p>
                                <p class="mt-1 max-w-md text-xs text-muted-foreground">Transparent PNG or WebP of the frame <strong>straight from the front</strong>, cropped tight from hinge to hinge, lenses level, max 2 MB. Shown on the shopper's face instead of the drawn frame.</p>
                            </div>
                            <div class="grid h-16 w-36 shrink-0 place-items-center rounded-lg border border-border bg-[repeating-conic-gradient(#eee_0_25%,#fff_0_50%)] bg-[length:12px_12px]">
                                <img x-show="tryOn && !remove" :src="tryOn" alt="Try-on preview" class="max-h-14 max-w-32 object-contain">
                                <span x-show="!tryOn || remove" class="text-[0.6rem] text-muted-foreground">Drawn frame</span>
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-3 text-xs">
                            <label class="btn-ghost cursor-pointer px-3 py-1.5"><x-lucide-upload class="h-3.5 w-3.5" /> Choose image
                                <input type="file" name="try_on_image" accept="image/png,image/webp" class="hidden" @change="const f = $event.target.files[0]; if (f) { tryOn = URL.createObjectURL(f); remove = false }">
                            </label>
                            @if ($product->try_on_image)
                                <label class="flex items-center gap-1.5 text-muted-foreground"><input type="checkbox" name="remove_try_on_image" value="1" x-model="remove"> Remove and use the drawn frame</label>
                            @endif
                        </div>
                        @error('try_on_image')<p class="mt-2 text-xs text-destructive">{{ $message }}</p>@enderror
                    </div>

                    <div class="mt-6 border-t border-border pt-5">
                        <p class="text-sm font-medium">Live camera try-on</p>
                        <p class="mt-1 max-w-xl text-xs text-muted-foreground">Fine-tune how the 3D frame sits on shoppers' faces (uses the 3D model when there is one). Open the product's Try On → Live camera to check, then adjust here.</p>
                        <div class="mt-3 grid gap-4 sm:grid-cols-3">
                            <x-admin.field label="Size (× real frame width)" name="try_on_scale"><input type="number" step="0.01" min="0.7" max="1.3" name="try_on_scale" value="{{ old('try_on_scale', $product->try_on_scale ?? 1) }}" class="input"></x-admin.field>
                            <x-admin.field label="Height (mm, + is higher)" name="try_on_offset_y"><input type="number" step="0.5" min="-15" max="15" name="try_on_offset_y" value="{{ old('try_on_offset_y', $product->try_on_offset_y ?? 0) }}" class="input"></x-admin.field>
                            <label class="flex items-center gap-2 self-end pb-2 text-sm"><input type="checkbox" name="try_on_flip" value="1" @checked(old('try_on_flip', $product->try_on_flip))> Model faces backwards (turn 180°)</label>
                        </div>
                    </div>
                </x-admin.panel>

                <x-admin.panel title="Pricing & margins">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-admin.field label="Retail price (USD)" name="price"><input type="number" step="0.01" min="0" name="price" x-model.number="price" required class="input"></x-admin.field>
                        <x-admin.field label="Unit cost" name="cost"><input type="number" step="0.01" min="0" name="cost" x-model.number="cost" required class="input"></x-admin.field>
                        <x-admin.field label="Gross margin"><div class="rounded-md bg-secondary px-3 py-2 font-mono text-sm" x-text="(price > 0 ? Math.round((price - cost) / price * 100) : 0) + '%'"></div></x-admin.field>
                        <x-admin.field label="Original / MRP price (shows strikethrough & % off)" name="compare_at_price"><input type="number" step="0.01" min="0" name="compare_at_price" value="{{ old('compare_at_price', $product->compare_at_price) }}" class="input"></x-admin.field>
                        <x-admin.field label="Offer text (e.g. with Free Anti-Glare lenses)" name="offer_text" class="sm:col-span-2"><input name="offer_text" value="{{ old('offer_text', $product->offer_text) }}" maxlength="120" class="input"></x-admin.field>
                    </div>
                </x-admin.panel>

                <x-admin.panel title="Optical specs & finishes">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-admin.field label="Material" name="material"><input name="material" x-model="material" class="input"></x-admin.field>
                        <x-admin.field label="Shape" name="shape"><input name="shape" x-model="shape" class="input" placeholder="e.g. Architectural Square, Aviator, Rimless"></x-admin.field>
                        <x-admin.field label="Frame type" name="frame_type">
                            <select name="frame_type" class="input">@foreach (config('store.frame_types') as $key => $label)<option value="{{ $key }}" @selected(old('frame_type', $product->frame_type) === $key)>{{ $label }}</option>@endforeach</select>
                        </x-admin.field>
                        <x-admin.field label="Temple material" name="temple_material"><input name="temple_material" value="{{ old('temple_material', $product->temple_material) }}" class="input"></x-admin.field>
                        <x-admin.field label="Colour name (swatch label, e.g. Navy)" name="color_name"><input name="color_name" value="{{ old('color_name', $product->color_name) }}" class="input"></x-admin.field>
                        <x-admin.field label="Colour variant group (same key = colour options of one frame)" name="variant_group"><input name="variant_group" value="{{ old('variant_group', $product->variant_group) }}" placeholder="e.g. apex" class="input font-mono"></x-admin.field>
                        <x-admin.field label="Color description" name="color"><input name="color" value="{{ old('color', $product->color) }}" class="input"></x-admin.field>
                        <x-admin.field label="Lens tag" name="lens_tag"><input name="lens_tag" value="{{ old('lens_tag', $product->lens_tag) }}" class="input"></x-admin.field>
                        <x-admin.field label="Weight" name="weight"><input name="weight" value="{{ old('weight', $product->weight) }}" class="input" placeholder="12 g"></x-admin.field>
                        <x-admin.field label="Sort order" name="sort_order"><input type="number" name="sort_order" value="{{ old('sort_order', $product->sort_order ?? 0) }}" class="input"></x-admin.field>
                    </div>
                    <div class="mt-4">
                        <span class="field-label">Finish swatches</span>
                        <div class="flex flex-wrap items-center gap-2">
                            <template x-for="(color, i) in finishes" :key="i">
                                <div class="flex items-center gap-1 rounded-md border border-border p-1">
                                    <input type="color" :name="`finishes[${i}]`" x-model="finishes[i]" class="h-7 w-9 cursor-pointer rounded" :aria-label="`Finish ${i + 1}`">
                                    <button type="button" @click="finishes.splice(i, 1)" aria-label="Remove finish"><x-lucide-x class="h-3.5 w-3.5" /></button>
                                </div>
                            </template>
                            <button type="button" x-show="finishes.length < 6" @click="finishes.push('#8A9A8A')" class="btn-ghost btn-sm">+ Finish</button>
                        </div>
                        @error('finishes.*')<p class="mt-1 text-xs text-destructive">{{ $message }}</p>@enderror
                    </div>
                </x-admin.panel>

                <x-admin.panel title="Inventory controls">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-admin.field label="Stock on hand" name="stock"><input type="number" min="0" name="stock" value="{{ old('stock', $product->stock) }}" required class="input"></x-admin.field>
                        <x-admin.field label="Low-stock threshold" name="low_stock_threshold"><input type="number" min="0" name="low_stock_threshold" value="{{ old('low_stock_threshold', $product->low_stock_threshold) }}" required class="input"></x-admin.field>
                        <x-admin.field label="Status" name="status">
                            <select name="status" class="input"><option value="active" @selected(old('status', $product->status) === 'active')>Active</option><option value="draft" @selected(old('status', $product->status) === 'draft')>Draft (hidden)</option></select>
                        </x-admin.field>
                    </div>
                </x-admin.panel>
            </div>

            <div class="space-y-6">
                <x-admin.panel title="Live preview card" class="h-fit xl:sticky xl:top-20">
                    <div class="rounded-xl border border-border p-3">
                        <div class="grid aspect-[4/3] place-items-center overflow-hidden rounded-lg bg-secondary text-xs text-muted-foreground">
                            <img x-show="preview" :src="preview" alt="" class="h-full w-full object-cover"><span x-show="!preview">No image yet</span>
                        </div>
                        <div class="mt-2 flex gap-1"><template x-for="c in finishes"><span class="h-2.5 w-2.5 rounded-full border border-border" :style="{ background: c }"></span></template></div>
                        <h3 class="mt-2 font-semibold" x-text="name || 'Untitled frame'"></h3>
                        <p class="text-xs text-muted-foreground" x-text="`${shape || '—'} · ${material || '—'}`"></p>
                        <p class="mt-2 font-display font-semibold" x-text="`$${price || 0} USD`"></p>
                    </div>
                </x-admin.panel>
                @if ($editing)
                    <x-admin.panel title="Audit activity log">
                        <ul class="space-y-3 text-sm">
                            @forelse ($activity as $log)
                                <li class="flex justify-between gap-3 border-b border-border pb-2"><span>{{ $log->action }}<span class="block text-xs text-muted-foreground">{{ $log->user?->email ?? 'system' }}</span></span><span class="text-xs text-muted-foreground">{{ $log->created_at->format('d M') }}</span></li>
                            @empty
                                <li class="text-muted-foreground">No changes recorded yet.</li>
                            @endforelse
                        </ul>
                    </x-admin.panel>
                @endif
            </div>
        </div>
    </form>

    @if ($editing)
        <x-admin.panel :title="'360° view ('.$product->spinFrames->count().' frames)'">
            <div class="grid gap-6 lg:grid-cols-[1fr_1.2fr]">
                <div class="space-y-3 text-sm">
                    <p class="text-muted-foreground">Upload a sequence of photos taken around the frame on a turntable at equal angles — <strong>24 to 72 frames</strong> (e.g. one every 10°). Frames are ordered by file name, so name them <code>001.jpg, 002.jpg…</code>. Without photos the store renders a live 3D model of the frame, shaped and coloured from the product details.</p>
                    <form method="POST" action="{{ route('admin.products.spin.store', $product) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3 rounded-lg border border-dashed border-border p-4">
                        @csrf
                        <x-admin.field label="Spin frames (8–120 images, 2 MB each)" name="frames"><input type="file" name="frames[]" accept="image/*" multiple required class="text-sm"></x-admin.field>
                        <button class="btn btn-sm">{{ $product->spinFrames->isEmpty() ? 'Upload 360° view' : 'Replace 360° view' }}</button>
                        @error('frames.*')<p class="w-full text-xs text-destructive">{{ $message }}</p>@enderror
                    </form>
                    @if ($product->spinFrames->isNotEmpty())
                        <form method="POST" action="{{ route('admin.products.spin.destroy', $product) }}" onsubmit="return confirm('Remove all 360° frames?')">@csrf @method('DELETE')<button class="text-xs text-destructive underline">Remove 360° frames</button></form>
                    @endif
                </div>
                <div class="overflow-hidden rounded-xl border border-border bg-secondary">
                    <x-site.spin-viewer :product="$product" />
                    <p class="border-t border-border px-3 py-2 text-xs text-muted-foreground">{{ count($product->spinUrls()) ? 'Live preview of the uploaded sequence.' : 'Preview: live 3D model (no photos uploaded yet).' }}</p>
                </div>
            </div>
        </x-admin.panel>

        <x-admin.panel :title="'Gallery & Style Stories ('.$product->images->count().' images)'">
            <p class="mb-4 text-xs text-muted-foreground">Recommended order: front → angled → side → on model → top → case. The primary photo leads the product page gallery; lifestyle photos appear in Style Stories. Each upload is converted to WebP/JPEG at 1080, 720 and 480 px.</p>
            {{-- React images manager (resources/js/components/admin/ProductImagesManager.tsx) --}}
            <div id="product-images-manager" data-props="{{ json_encode([
                'indexUrl' => route('admin.products.images.index', $product),
                'uploadUrl' => route('admin.products.images.store', $product),
                'reorderUrl' => route('admin.products.images.reorder', $product),
                'spinSetUrl' => route('admin.products.spin-sets.store', $product),
                'modelUrl' => route('admin.products.model.store', $product),
                'viewTypes' => \App\Enums\ImageViewType::options(),
            ]) }}">
                <p class="text-sm text-muted-foreground">Loading gallery manager…</p>
            </div>
            @viteReactRefresh
            @vite('resources/js/admin-images.tsx')
        </x-admin.panel>

        <x-admin.panel :title="'Customer reviews ('.$reviews->count().')'">
            <div class="space-y-3">
                @forelse ($reviews as $review)
                    <div class="flex items-start justify-between gap-4 rounded-lg border border-border p-3 text-sm">
                        <div><p class="font-medium">{{ $review->reviewer_name }} · {{ str_repeat('★', $review->rating) }}@if ($review->verified_buyer) <span class="text-xs text-muted-foreground">· verified</span>@endif</p><p class="mt-1 text-muted-foreground">{{ $review->body }}</p></div>
                        <form method="POST" action="{{ route('admin.reviews.destroy', $review) }}" onsubmit="return confirm('Remove this review?')">@csrf @method('DELETE')<button class="text-xs text-destructive underline">Remove</button></form>
                    </div>
                @empty
                    <p class="text-sm text-muted-foreground">No reviews yet.</p>
                @endforelse
            </div>
        </x-admin.panel>
        <form method="POST" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('Delete {{ addslashes($product->name) }}? Past orders keep their line items.')" class="flex justify-end">
            @csrf @method('DELETE')
            <button class="text-xs text-destructive underline">Delete product</button>
        </form>
    @endif
</x-layouts.admin>
