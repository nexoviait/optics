<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EmailTemplate extends Model
{
    protected $fillable = ['slug', 'name', 'description', 'hook', 'category', 'locale', 'subject', 'preheader', 'blocks', 'status'];

    /** Sample values substituted into merge tokens for previews. */
    public const SAMPLE_TOKENS = [
        '{{customer.first_name}}' => 'Christian',
        '{{order.id}}' => 'RF-48211',
        '{{lens.index}}' => '1.67',
        '{{tracking.url}}' => 'track.refra.com/RF-48211',
    ];

    protected function casts(): array
    {
        return ['blocks' => 'array'];
    }

    protected static function booted(): void
    {
        static::saving(function (EmailTemplate $template) {
            $template->slug = $template->slug ?: Str::slug($template->name).'-'.Str::lower(Str::random(4));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public static function fillTokens(?string $text): string
    {
        return self::replaceTokens($text, self::SAMPLE_TOKENS);
    }

    /** @param array<string, string> $tokens */
    public static function replaceTokens(?string $text, array $tokens): string
    {
        return strtr((string) $text, $tokens);
    }
}
