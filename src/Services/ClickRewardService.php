<?php

namespace Modules\Custom\AdFloat\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Custom\AdFloat\Models\AdFloatItem;
use Modules\Custom\AdFloat\Models\AdFloatMileageReward;
use Modules\Custom\AdFloat\Models\AdFloatSetting;

/**
 * 플로팅 광고 클릭 마일리지 적립
 *
 * - 회원만, (회원, 광고, 날짜) 당 1번 → target_key "item:{id}:{Y-m-d}"
 * - 하루 적립 횟수 한도 (click_reward_daily_limit, 0 = 무제한)
 * - 설정 기본값 꺼짐. 이커머스 마일리지를 쓸 수 없으면 아무것도 하지 않음.
 * - 익명 클릭 통계(track)와는 별개이며, 실패해도 클릭(이동)은 절대 막지 않음.
 */
class ClickRewardService
{
    public const ACTION = 'click_reward';

    public function __construct(private MileageBridge $mileage) {}

    /**
     * @return array{awarded: bool, amount: int, reason: string}
     */
    public function reward(?int $userId, int $itemId): array
    {
        $none = fn (string $reason) => ['awarded' => false, 'amount' => 0, 'reason' => $reason];

        if (! $userId) {
            return $none('guest');
        }

        try {
            if (! Schema::hasTable('custom_ad_float_mileage_rewards')) {
                return $none('disabled');
            }
            $cfg = AdFloatSetting::current()->clickRewardSettings();
            $amount = (int) $cfg['click_reward_amount'];
            if (! $cfg['click_reward_enabled'] || $amount <= 0) {
                return $none('disabled');
            }

            $item = AdFloatItem::query()->whereKey($itemId)->where('enabled', true)->first();
            if (! $item || trim((string) ($item->target_url ?? '')) === '') {
                return $none('invalid_item');
            }
            if (! $this->mileage->available()) {
                return $none('unavailable');
            }

            $targetKey = 'item:'.$item->id.':'.now()->format('Y-m-d');
            $exists = AdFloatMileageReward::query()
                ->where('user_id', $userId)->where('action', self::ACTION)->where('target_key', $targetKey)
                ->exists();
            if ($exists) {
                return $none('duplicate');
            }

            $limit = (int) $cfg['click_reward_daily_limit'];
            if ($limit > 0) {
                $today = AdFloatMileageReward::query()
                    ->where('user_id', $userId)->where('action', self::ACTION)
                    ->where('created_at', '>=', now()->startOfDay())
                    ->count();
                if ($today >= $limit) {
                    return $none('daily_limit');
                }
            }

            $title = trim((string) ($item->title ?? ''));
            $desc = '[광고] 플로팅 광고 클릭 적립'.($title !== '' ? ' ('.mb_substr($title, 0, 40).')' : '');

            DB::transaction(function () use ($userId, $targetKey, $amount, $item, $desc) {
                $row = AdFloatMileageReward::query()->create([
                    'user_id' => $userId,
                    'action' => self::ACTION,
                    'target_key' => $targetKey,
                    'amount' => $amount,
                ]);
                $txId = $this->mileage->earn($userId, $amount, '[custom-ad_float] ad click #'.$item->id, $desc);
                if ($txId === null) {
                    throw new \RuntimeException('mileage unavailable');
                }
                if ($txId > 0) {
                    $row->transaction_id = $txId;
                    $row->save();
                }
            });

            return ['awarded' => true, 'amount' => $amount, 'reason' => 'ok'];
        } catch (QueryException $e) {
            if (str_contains(strtolower($e->getMessage()), 'unique') || (string) $e->getCode() === '23000') {
                return $none('duplicate');
            }
            Log::warning('[custom-ad_float] click reward failed', ['user_id' => $userId, 'item_id' => $itemId, 'error' => $e->getMessage()]);

            return $none('error');
        } catch (\Throwable $e) {
            Log::warning('[custom-ad_float] click reward failed', ['user_id' => $userId, 'item_id' => $itemId, 'error' => $e->getMessage()]);

            return $none('error');
        }
    }
}
