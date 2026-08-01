<?php

namespace App\Support;

final class PublicMediaUrl
{
    public static function storage(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'https://') || str_starts_with($path, 'http://')) {
            return $path;
        }

        if (str_starts_with($path, '/storage/')) {
            return $path;
        }

        return '/storage/'.ltrim($path, '/');
    }

    public static function versionedStorage(?string $path, string|int|null $version): ?string
    {
        $url = self::storage($path);

        if ($url === null || $version === null || ! str_starts_with($url, '/storage/')) {
            return $url;
        }

        $version = trim((string) $version);

        if ($version === '') {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').'v='.rawurlencode($version);
    }
}
