<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class FrameSize extends Model
{
    protected $fillable = ['code', 'width', 'lens_width', 'bridge', 'temple', 'face', 'sort_order'];

    public static function ordered(): Collection
    {
        return static::query()->orderBy('sort_order')->get();
    }

    /** Nearest guide frame width to a measured face width (110–170 mm). */
    public static function matchFaceWidth(float $width): ?self
    {
        if ($width < 110 || $width > 170) {
            return null;
        }

        return static::ordered()->sortBy(fn (self $size) => abs($size->width - $width))->first();
    }

    public function caliber(): string
    {
        return "{$this->lens_width}-{$this->bridge}-{$this->temple}";
    }
}
