<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Key/value store settings backed by the `settings` table and cached forever
 * until an admin changes them.
 */
class Settings
{
    private const CACHE_KEY = 'store.settings';

    /** @var array<string, mixed>|null */
    private static ?array $loaded = null;

    public static function all(): array
    {
        if (self::$loaded !== null) {
            return self::$loaded;
        }

        try {
            return self::$loaded = Cache::rememberForever(self::CACHE_KEY, function () {
                if (! Schema::hasTable('settings')) {
                    return [];
                }

                return Setting::query()->pluck('value', 'key')->all();
            });
        } catch (Throwable) {
            return self::$loaded = [];
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return filter_var(self::get($key, $default), FILTER_VALIDATE_BOOLEAN);
    }

    /** @param array<string, mixed> $values */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        self::$loaded = null;
    }
}
