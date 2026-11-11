<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductSpinFrame extends Model
{
    protected $fillable = ['product_id', 'path', 'position'];

    protected static function booted(): void
    {
        static::deleted(function (ProductSpinFrame $frame) {
            if (! Str::startsWith($frame->path, 'images/')) {
                Storage::disk('public')->delete($frame->path);
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function url(): string
    {
        return Str::startsWith($this->path, 'images/') ? asset($this->path) : asset('storage/'.$this->path);
    }
}
