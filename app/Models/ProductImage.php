<?php

namespace App\Models;

use App\Enums\ImageViewType;
use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'path', 'alt', 'view_type', 'width', 'height', 'is_primary', 'renditions', 'is_lifestyle', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_lifestyle' => 'boolean',
            'is_primary' => 'boolean',
            'view_type' => ImageViewType::class,
            'renditions' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Remove uploaded files (and generated renditions) with the record; bundled demo images under public/images are kept.
        static::deleted(function (ProductImage $image) {
            Storage::disk('public')->delete($image->generatedPaths());
            if (! Str::startsWith($image->path, 'images/')) {
                Storage::disk('public')->delete($image->path);
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function url(): string
    {
        return MediaUrl::for($this->path);
    }

    /**
     * Responsive sources by format and width, e.g. ['webp' => [480 => url, …], 'jpg' => […]].
     * Until the processing job has run, the original is offered as the only JPEG source.
     *
     * @return array{webp: array<int, string>, jpg: array<int, string>}
     */
    public function sources(): array
    {
        $sources = ['webp' => [], 'jpg' => []];
        foreach (['webp', 'jpg'] as $format) {
            foreach ($this->renditions[$format] ?? [] as $width => $path) {
                $sources[$format][(int) $width] = MediaUrl::for($path);
            }
            ksort($sources[$format]);
        }
        if (! $sources['jpg']) {
            $sources['jpg'][$this->width ?: 1080] = $this->url();
        }

        return $sources;
    }

    public function thumbUrl(): string
    {
        return MediaUrl::for($this->renditions['thumb'] ?? null) ?? $this->url();
    }

    /** @return list<string> storage paths of every generated rendition */
    public function generatedPaths(): array
    {
        $r = $this->renditions ?? [];

        return array_values(array_filter([...array_values($r['webp'] ?? []), ...array_values($r['jpg'] ?? []), $r['thumb'] ?? null]));
    }
}
