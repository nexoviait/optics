<?php

namespace App\Models;

use App\Support\FrameModel;
use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $fillable = [
        'slug', 'variant_group', 'name', 'brand', 'model_no', 'series', 'badge', 'category', 'gender',
        'age_group', 'frame_type', 'price', 'compare_at_price', 'offer_text', 'cost', 'sku', 'stock',
        'low_stock_threshold', 'status', 'image', 'try_on_image', 'shape', 'color', 'color_name', 'material',
        'temple_material', 'lens_tag', 'weight', 'finishes', 'description', 'sort_order',
        'try_on_flip', 'try_on_scale', 'try_on_offset_y',
    ];

    /** Every storefront card renders the 360° viewer, so load its frames with the product. */
    protected $with = ['spinFrames', 'spinSet'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'cost' => 'decimal:2',
            'finishes' => 'array',
            'stock' => 'integer',
            'low_stock_threshold' => 'integer',
            'views' => 'integer',
            'try_on_flip' => 'boolean',
            'try_on_scale' => 'float',
            'try_on_offset_y' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            $product->slug = $product->slug ?: Str::slug($product->name);
        });
        static::deleted(function (Product $product) {
            if ($product->model_3d_path) {
                Storage::disk('public')->delete($product->model_3d_path);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('stock', '<', 'low_stock_threshold');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Product photos (not lifestyle), primary first then in the admin's order. */
    public function galleryImages(): HasMany
    {
        return $this->hasMany(ProductImage::class)->where('is_lifestyle', false)
            ->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id');
    }

    public function lifestyleImages(): HasMany
    {
        return $this->images()->where('is_lifestyle', true);
    }

    public function spinFrames(): HasMany
    {
        return $this->hasMany(ProductSpinFrame::class)->orderBy('position');
    }

    /** 360° sequence uploaded as a ZIP (see ProductSpinSet); preferred over individually uploaded frames. */
    public function spinSet(): HasOne
    {
        return $this->hasOne(ProductSpinSet::class);
    }

    /**
     * Turntable photo URLs for the 360° viewer. A real spin needs at least 8
     * photos; with fewer the Blade viewer renders the live 3D frame model instead
     * and the React product page hides its 360° view.
     *
     * @return list<string>
     */
    public function spinUrls(): array
    {
        if ($this->spinSet && $this->spinSet->frame_count >= 8) {
            return $this->spinSet->urls();
        }

        return $this->spinFrames->count() >= 8
            ? $this->spinFrames->map(fn (ProductSpinFrame $frame) => $frame->url())->values()->all()
            : [];
    }

    /**
     * URL of the 3D model (.glb) for the product page's 360° view: the one
     * uploaded in the admin, else public/models/{sku}.glb (for adding many
     * models at once by copying files); null when there is none.
     */
    public function model3dUrl(): ?string
    {
        if ($this->model_3d_path) {
            return MediaUrl::for($this->model_3d_path);
        }

        return $this->modelFolderUrl('glb');
    }

    /** 'upload', 'folder' or null: where model3dUrl() comes from. */
    public function model3dSource(): ?string
    {
        return $this->model_3d_path ? 'upload' : ($this->modelFolderUrl('glb') ? 'folder' : null);
    }

    /**
     * Optional Apple USDZ model at public/models/{sku}.usdz for AR Quick Look
     * on iPhone/iPad. Without one, the viewer converts the .glb on the device.
     */
    public function model3dIosUrl(): ?string
    {
        return $this->model3dUrl() ? $this->modelFolderUrl('usdz') : null;
    }

    private function modelFolderUrl(string $extension): ?string
    {
        // SKUs become file names, so only allow safe characters.
        if (! $this->sku || ! preg_match('/^[A-Za-z0-9._-]+$/', $this->sku)) {
            return null;
        }
        $path = "models/{$this->sku}.{$extension}";

        return is_file(public_path($path)) ? asset($path) : null;
    }

    /** 3D wireframe used for the live-rendered 360° view. */
    public function spinModel(): array
    {
        return FrameModel::for($this);
    }

    /**
     * The 3D try-ons: the .glb when there is one, else a frame built from the product's shape,
     * plus the admin's fit adjustments.
     *
     * @return array{model: ?string, frame: array, flip: bool, scale: float, offsetY: float}
     */
    public function liveTryOnSettings(): array
    {
        return [
            'model' => $this->model3dUrl(),
            'frame' => $this->spinModel(),
            'flip' => (bool) $this->try_on_flip,
            'scale' => (float) ($this->try_on_scale ?? 1),
            'offsetY' => (float) ($this->try_on_offset_y ?? 0),
        ];
    }

    /** Active colour variants of the same frame, including this product. */
    public function variants(): Collection
    {
        if (! $this->variant_group) {
            return collect([$this]);
        }

        return static::query()->active()->where('variant_group', $this->variant_group)->ordered()->get();
    }

    /** True when a valid "was" price above the selling price is set. */
    public function isDiscounted(): bool
    {
        return $this->compare_at_price !== null && (float) $this->compare_at_price > (float) $this->price;
    }

    public function discountPercent(): int
    {
        return $this->isDiscounted()
            ? (int) round((1 - (float) $this->price / (float) $this->compare_at_price) * 100)
            : 0;
    }

    public function frameTypeLabel(): string
    {
        return config('store.frame_types.'.$this->frame_type, 'Full Rim');
    }

    public function genderLabel(): string
    {
        return config('store.genders.'.$this->gender, 'Unisex');
    }

    /** e.g. "Navy Full Rim Rectangle". */
    public function styleLine(): string
    {
        return trim(implode(' ', array_filter([$this->color_name, $this->frameTypeLabel(), $this->shape])));
    }

    public function imageUrl(): string
    {
        return MediaUrl::for($this->image ?: 'images/frame-apex.jpg');
    }

    /** @return list<string> */
    public function finishColors(): array
    {
        return $this->finishes ?: ['#0F1113'];
    }

    public function isLowStock(): bool
    {
        return $this->stock < $this->low_stock_threshold;
    }

    public function isRimless(): bool
    {
        return $this->frame_type === 'rimless' || Str::contains(Str::lower((string) $this->shape), 'rimless');
    }

    public function marginPercent(): int
    {
        $price = (float) $this->price;

        return $price > 0 ? (int) round((($price - (float) $this->cost) / $price) * 100) : 0;
    }

    public function unitsSold(): int
    {
        return (int) $this->orderItems()
            ->whereHas('order', fn (Builder $q) => $q->where('status', '!=', 'Cancelled'))
            ->sum('qty');
    }

    /**
     * How the 2D try-on draws this frame (components/try-on/FrameOverlay.tsx).
     *
     * @return array{image: ?string, kind: string, thin: bool, rimless: bool, tinted: bool}
     */
    public function tryOnStyle(): array
    {
        $shape = Str::lower((string) $this->shape);

        return [
            'image' => $this->try_on_image ? asset('storage/'.$this->try_on_image) : null,
            'kind' => match (true) {
                Str::contains($shape, ['hex', 'geometric']) => 'hex',
                Str::contains($shape, ['aviator', 'pilot', 'teardrop']) => 'aviator',
                Str::contains($shape, 'cat') => 'cat',
                Str::contains($shape, 'panto') => 'panto',
                Str::contains($shape, ['round', 'oval']) => 'round',
                Str::contains($shape, ['bold', 'wayfarer']) => 'bold',
                default => 'rect',
            },
            // Any metal in the build means a fine wire rim; pure acetate is drawn bold.
            'thin' => (bool) preg_match('/titanium|metal|steel|wire|gold|alloy|\bti\b/i', (string) $this->material),
            'rimless' => $this->isRimless(),
            'tinted' => $this->category === 'sunglasses',
        ];
    }

    /** Payload used by Alpine components on the storefront. */
    public function toClientArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'category' => $this->category,
            'price' => (float) $this->price,
            'image' => $this->imageUrl(),
            'shape' => $this->shape,
            'material' => $this->material,
            'color' => $this->color,
            'finishes' => $this->finishColors(),
            'try_on' => $this->tryOnStyle(),
            'url' => route('products.show', $this),
        ];
    }
}
