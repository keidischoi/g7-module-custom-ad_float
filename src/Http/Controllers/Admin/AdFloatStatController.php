<?php

namespace Modules\Custom\AdFloat\Http\Controllers\Admin;

use App\Http\Controllers\Api\Base\AdminBaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Custom\AdFloat\Services\AdFloatStatService;

class AdFloatStatController extends AdminBaseController
{
    public function __construct(private AdFloatStatService $stats)
    {
        parent::__construct();
    }

    public function show(Request $request): JsonResponse
    {
        try {
            $range = $this->stats->normalizeRange((string) $request->query('range', '7d'));

            return $this->success(
                'custom-ad_float::messages.stats.fetch_success',
                $this->stats->aggregate($range)
            );
        } catch (\Exception $e) {
            return $this->error('custom-ad_float::messages.stats.fetch_failed', 500, $e->getMessage());
        }
    }
}
