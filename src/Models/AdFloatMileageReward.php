<?php

namespace Modules\Custom\AdFloat\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 마일리지 적립 원장 행
 */
class AdFloatMileageReward extends Model
{
    protected $table = 'custom_ad_float_mileage_rewards';

    protected $fillable = [
        'user_id', 'action', 'target_key', 'amount', 'transaction_id', 'reclaimed_at', 'reclaimed_amount',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'amount' => 'integer',
        'transaction_id' => 'integer',
        'reclaimed_at' => 'datetime',
        'reclaimed_amount' => 'integer',
    ];
}
