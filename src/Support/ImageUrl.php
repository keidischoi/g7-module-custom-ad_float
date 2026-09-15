<?php

namespace Modules\Custom\AdFloat\Support;

/**
 * Build public <img src> values for custom_ad_float_items.image_path.
 *
 * Upload mode stores Laravel public-disk relative paths from
 * store('custom-ad-float', 'public') — e.g. custom-ad-float/abc.jpg
 * on disk at storage/app/public/custom-ad-float/abc.jpg.
 *
 * Do not use Storage::disk('public')->url() / APP_URL: Synology reverse
 * proxies often have APP_URL on an internal :8482 Web Station port, which
 * makes absolute image URLs appear broken ("끊김") after a successful save.
 */
final class ImageUrl
{
    public const STORAGE_WEB_PREFIX = '/storage/';

    public const MEDIA_WEB_PREFIX = '/api/modules/custom-ad_float/media/';

    /**
     * Public URL for an <img src>. http(s) and protocol-relative URL-mode
     * values are returned unchanged. Storage-relative upload paths become a
     * same-origin relative URL (no host).
     */
    public static function publicUrl(string $imagePath): string
    {
        $raw = trim($imagePath);
        if ($raw === '') {
            return '';
        }
        $normalized = self::normalizeSlashes($raw);

        $relative = self::diskRelativePath($normalized);
        if ($relative !== null) {
            return self::mediaUrl($relative);
        }

        if (self::isRemoteUrl($normalized)) {
            return $raw;
        }

        if (str_starts_with($normalized, '/')) {
            return $normalized;
        }

        return self::mediaUrl(ltrim($normalized, '/'));
    }

    /**
     * Laravel public-disk web path (/storage/{relative}) for a stored file.
     * Empty when $imagePath is a remote URL-mode value.
     */
    public static function storageUrl(string $imagePath): string
    {
        $relative = self::diskRelativePath($imagePath);
        if ($relative === null) {
            return '';
        }

        return self::STORAGE_WEB_PREFIX.$relative;
    }

    public static function mediaUrl(string $relative): string
    {
        $relative = ltrim(self::normalizeSlashes($relative), '/');

        return self::MEDIA_WEB_PREFIX.self::encodePath($relative);
    }

    public static function isRemoteUrl(string $path): bool
    {
        $path = self::normalizeSlashes(trim($path));

        return (bool) preg_match('#^https?://#i', $path) || str_starts_with($path, '//');
    }

    /**
     * Public-disk relative path (custom-ad-float/file.jpg) or null when this
     * is a remote/site URL that should not be rewritten.
     */
    public static function diskRelativePath(string $imagePath): ?string
    {
        $path = trim($imagePath);
        if ($path === '') {
            return null;
        }
        $path = self::normalizeSlashes($path);
        $path = preg_replace('#^file://#i', '', $path) ?? $path;

        if (preg_match('#^https?://#i', $path)) {
            return null;
        }

        if (preg_match('#(?:^|/)storage/app/public/(.+)$#', $path, $m)) {
            return self::normalizeRelative($m[1]);
        }

        if (preg_match('#^/?storage/(.+)$#', $path, $m)) {
            $rest = $m[1];
            if (str_starts_with($rest, 'app/public/')) {
                return self::normalizeRelative(substr($rest, strlen('app/public/')));
            }

            return self::normalizeRelative($rest);
        }

        if (preg_match('#(?:^|/)(?:app/)?public/(custom-ad-float/.+)$#', $path, $m)) {
            return self::normalizeRelative($m[1]);
        }

        if (str_starts_with($path, 'custom-ad-float/') || $path === 'custom-ad-float') {
            return self::normalizeRelative($path);
        }

        // Unix/NAS absolute filesystem path that still contains our folder.
        if (str_starts_with($path, '/') && str_contains($path, 'custom-ad-float/')) {
            if (preg_match('#(custom-ad-float/.+)$#', $path, $m)) {
                return self::normalizeRelative($m[1]);
            }
        }

        return null;
    }

    public static function isSafePublicRelativePath(string $relative): bool
    {
        $relative = ltrim(self::normalizeSlashes($relative), '/');
        if ($relative === '' || str_contains($relative, '..') || str_contains($relative, "\0")) {
            return false;
        }
        if (str_starts_with($relative, '/') || preg_match('#^[a-zA-Z]:/#', $relative)) {
            return false;
        }

        return str_starts_with($relative, 'custom-ad-float/') && $relative !== 'custom-ad-float/';
    }

    private static function normalizeRelative(string $relative): ?string
    {
        $relative = ltrim(self::normalizeSlashes($relative), '/');
        if (! self::isSafePublicRelativePath($relative)) {
            return null;
        }

        return $relative;
    }

    private static function normalizeSlashes(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    private static function encodePath(string $relative): string
    {
        $parts = explode('/', $relative);
        $encoded = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $encoded[] = rawurlencode($part);
        }

        return implode('/', $encoded);
    }
}
