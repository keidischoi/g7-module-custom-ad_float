<?php

namespace Modules\Custom\AdFloat\Http\Controllers\Public;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Custom\AdFloat\Services\ClickRewardService;

/**
 * 플로팅 광고 클릭 마일리지 적립 (로그인 회원 전용, 익명 track 통계와 별개)
 *
 * POST /api/modules/custom-ad_float/ads/{id}/click-reward (auth:sanctum)
 * 설정 꺼짐·한도 초과·중복이어도 오류 없이 awarded=false 로 응답합니다.
 */
class ClickRewardController extends Controller
{
    public function store(Request $request, int $id, ClickRewardService $rewards): JsonResponse
    {
        $user = $request->user();
        $result = $rewards->reward($user ? (int) $user->id : null, $id);

        return response()->json([
            'success' => true,
            'message' => $result['awarded']
                ? __('custom-ad_float::messages.click_reward.awarded', ['amount' => number_format($result['amount'])])
                : __('custom-ad_float::messages.click_reward.skipped'),
            'data' => $result,
        ], 200);
    }
}
