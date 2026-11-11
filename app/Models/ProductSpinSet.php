<?php

namespace App\Models;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A 360° turntable sequence stored as numbered frames: `path_pattern` holds a
 * {frame} placeholder that is replaced with 001, 002, … up to frame_count.
 */
class ProductSpinSet extends Model
{
    protected $fillable = ['product_id', 'frame_count', 'path_pattern'];

    protected function casts(): array
    {
        return ['frame_count' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleted(fn (ProductSpinSet $set) => Storage::disk('public')->delete($set->paths()));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public static function framePath(string $pattern, int $index): string
    {
        return str_replace('{frame}', str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT), $pattern);
    }

    /** @return list<string> */
    public function paths(): array
    {
        return array_map(fn (int $i) => self::framePath($this->path_pattern, $i), range(0, max(0, $this->frame_count - 1)));
    }

    /** @return list<string> */
    public function urls(): array
    {
        return array_map(fn (string $path) => MediaUrl::for($path), $this->paths());
    }
}
