<?php

namespace Modules\Custom\AdFloat\Http\Controllers\Public;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Custom\AdFloat\Services\AdFloatService;

class PayloadController extends Controller
{
    public function show(AdFloatService $service): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $service->payload(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'data' => ['settings' => ['enabled' => false], 'items' => [], 'windows' => []],
            ], 200);
        }
    }
}
