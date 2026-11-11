<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReview extends Model
{
    protected $fillable = ['user_id', 'product_id', 'reviewer_name', 'rating', 'title', 'body', 'is_published'];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'verified_buyer' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Verified status is derived server-side from delivered orders, never trusted from input.
        static::creating(function (ProductReview $review) {
            $review->verified_buyer = OrderItem::query()
                ->where('product_id', $review->product_id)
                ->whereHas('order', fn (Builder $q) => $q->where('user_id', $review->user_id)->where('status', 'Delivered'))
                ->exists();
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
