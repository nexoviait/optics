<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LensSku extends Model
{
    protected $fillable = [
        'sku', 'name', 'substrate', 'refractive_index', 'abbe', 'geometry', 'coatings', 'labs',
        'stock', 'low_stock_threshold',
    ];

    protected function casts(): array
    {
        return [
            'coatings' => 'array',
            'labs' => 'array',
            'abbe' => 'integer',
            'stock' => 'integer',
            'low_stock_threshold' => 'integer',
        ];
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('stock', '<', 'low_stock_threshold');
    }

    public function isLowStock(): bool
    {
        return $this->stock < $this->low_stock_threshold;
    }
}
