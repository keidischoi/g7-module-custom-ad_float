<?php

namespace Modules\Custom\AdFloat\Http\Controllers\Public;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Custom\AdFloat\Support\ImageUrl;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serve public-disk ad images without depending on APP_URL or storage:link.
 * Same origin as other module assets (/api/modules/custom-ad_float/...).
 */
class MediaController extends Controller
{
    private const MIME_BY_EXT = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];

    public function show(string $path): Response|StreamedResponse
    {
        $relative = ImageUrl::diskRelativePath($path) ?? ltrim(str_replace('\\', '/', $path), '/');
        if (! ImageUrl::isSafePublicRelativePath($relative)) {
            abort(404);
        }

        try {
            if (! Storage::disk('public')->exists($relative)) {
                abort(404);
            }
        } catch (\Throwable $e) {
            abort(404);
        }

        $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        $mime = self::MIME_BY_EXT[$ext] ?? null;
        if ($mime === null) {
            abort(404);
        }

        try {
            return Storage::disk('public')->response($relative, basename($relative), [
                'Content-Type' => $mime,
                'Cache-Control' => 'public, max-age=86400',
            ]);
        } catch (\Throwable $e) {
            abort(404);
        }
    }
}
