<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * CMS content page (FAQ, guides, legal). Body is Markdown; raw HTML is escaped on render.
 */
class Page extends Model
{
    protected $fillable = ['slug', 'title', 'meta_description', 'body', 'footer_group', 'is_published', 'sort_order'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (Page $page) {
            $page->slug = $page->slug ?: Str::slug($page->title);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function html(): string
    {
        return Str::markdown($this->body, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }
}
