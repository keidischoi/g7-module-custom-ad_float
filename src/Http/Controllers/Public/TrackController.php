<?php

namespace Modules\Custom\AdFloat\Http\Controllers\Public;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Custom\AdFloat\Models\AdFloatItem;
use Modules\Custom\AdFloat\Services\AdFloatStatService;
use Modules\Custom\AdFloat\Support\PagePath;

class TrackController extends Controller
{
    public function store(Request $request, AdFloatStatService $stats): JsonResponse
    {
        $key = 'caf-track:'.(string) $request->ip();
        if (RateLimiter::tooManyAttempts($key, 60)) {
            return $this->ok();
        }
        RateLimiter::hit($key, 60);

        try {
            $payload = $this->payload($request);
            $type = strtolower(trim((string) ($payload['type'] ?? '')));
            if (! in_array($type, ['impression', 'click'], true)) {
                return $this->ok();
            }

            $itemId = (int) ($payload['item_id'] ?? 0);
            if ($itemId < 1 || ! AdFloatItem::query()->where('id', $itemId)->exists()) {
                return $this->ok();
            }

            $stats->increment($type, $itemId, PagePath::normalize($payload['page_path'] ?? ''));
        } catch (\Throwable $e) {
            // Public beacon: never fail the page.
        }

        return $this->ok();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $json = $request->json()->all();
        if (! is_array($json) || $json === []) {
            $decoded = json_decode((string) $request->getContent(), true);
            $json = is_array($decoded) ? $decoded : [];
        }
        if ($json === []) {
            $json = $request->only(['type', 'item_id', 'page_path']);
        }

        return is_array($json) ? $json : [];
    }

    private function ok(): JsonResponse
    {
        return response()->json(['success' => true], 200);
    }
}
