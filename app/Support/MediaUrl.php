<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The one place media URLs are built, so pointing images at a CDN is a single
 * setting (MEDIA_URL). Bundled demo images live under public/images; uploads
 * live on the public disk (storage/app/public, served from /storage).
 */
class MediaUrl
{
    public static function for(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $relative = Str::startsWith($path, 'images/') ? $path : 'storage/'.$path;
        $cdn = config('store.media_url');

        return $cdn ? rtrim($cdn, '/').'/'.$relative : asset($relative);
    }

    /** Absolute filesystem path of a stored or bundled image (resolved through the disk, so fakes work too). */
    public static function localPath(string $path): string
    {
        return Str::startsWith($path, 'images/') ? public_path($path) : Storage::disk('public')->path($path);
    }
}
