<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisionSession extends Model
{
    protected $fillable = ['user_id', 'preferences', 'face_shape', 'size', 'top_product_id', 'top_score', 'converted'];

    protected function casts(): array
    {
        return [
            'preferences' => 'array',
            'converted' => 'boolean',
        ];
    }

    public function topProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'top_product_id');
    }
}
