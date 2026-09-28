<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 배너 클릭 마일리지 적립 설정 (기본 꺼짐).
     */
    public function up(): void
    {
        if (! Schema::hasTable('custom_ad_float_settings')) {
            return;
        }

        Schema::table('custom_ad_float_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('custom_ad_float_settings', 'click_reward_enabled')) {
                $table->boolean('click_reward_enabled')->default(false);
            }
            if (! Schema::hasColumn('custom_ad_float_settings', 'click_reward_amount')) {
                $table->unsignedInteger('click_reward_amount')->default(5);
            }
            if (! Schema::hasColumn('custom_ad_float_settings', 'click_reward_daily_limit')) {
                $table->unsignedInteger('click_reward_daily_limit')->default(5);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('custom_ad_float_settings')) {
            return;
        }
        foreach (['click_reward_enabled', 'click_reward_amount', 'click_reward_daily_limit'] as $col) {
            if (Schema::hasColumn('custom_ad_float_settings', $col)) {
                Schema::table('custom_ad_float_settings', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }
};
