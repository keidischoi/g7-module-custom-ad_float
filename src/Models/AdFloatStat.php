<?php

namespace Modules\Custom\AdFloat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AdFloatStat extends Model
{
    protected $table = 'custom_ad_float_stats';

    protected $fillable = [
        'item_id',
        'page_path',
        'impressions',
        'clicks',
        'stat_date',
    ];

    protected $casts = [
        'item_id' => 'integer',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'stat_date' => 'date',
    ];

    public static function tableReady(): bool
    {
        try {
            return Schema::hasTable('custom_ad_float_stats');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
