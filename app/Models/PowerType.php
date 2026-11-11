<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PowerType extends Model
{
    protected $fillable = ['code', 'label', 'description', 'price', 'needs_lens', 'needs_rx', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'needs_lens' => 'boolean',
            'needs_rx' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
