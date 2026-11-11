<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class PageSection extends Model
{
    protected $fillable = ['page', 'key', 'label', 'is_enabled', 'sort_order', 'content'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'content' => 'array',
        ];
    }

    public function scopeForPage(Builder $query, string $page): Builder
    {
        return $query->where('page', $page)->orderBy('sort_order');
    }

    /** Read a value from the section content using dot notation. */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->content ?? [], $key, $default);
    }
}
