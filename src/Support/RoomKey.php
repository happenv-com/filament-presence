<?php

declare(strict_types=1);

namespace Happenv\FilamentPresence\Support;

use Illuminate\Support\Str;

final class RoomKey
{
    /**
     * Derive a channel-safe room key from a request path (query string ignored
     * by callers). A short hash suffix guarantees uniqueness for paths that
     * would otherwise slugify identically.
     */
    public static function for(string $path): string
    {
        $normalised = '/' . mb_strtolower(trim(parse_url($path, PHP_URL_PATH) ?? $path, '/'));
        $slug = Str::slug(str_replace('/', '-', $normalised)) ?: 'root';
        $hash = substr(hash('xxh128', $normalised), 0, 10);

        return $slug . '-' . $hash;
    }
}
