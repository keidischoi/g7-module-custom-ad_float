<?php

namespace Modules\Custom\AdFloat\Support;

class PagePath
{
    public const MAX_LENGTH = 255;

    public static function normalize(mixed $path): string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '/';
        }
        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }
        if (strlen($path) > 1) {
            $path = rtrim($path, '/');
        }
        if ($path === '') {
            return '/';
        }
        if (strlen($path) > self::MAX_LENGTH) {
            $path = substr($path, 0, self::MAX_LENGTH);
        }

        return $path;
    }
}
