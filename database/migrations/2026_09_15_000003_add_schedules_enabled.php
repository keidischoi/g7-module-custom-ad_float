<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('custom_ad_float_settings') && ! Schema::hasColumn('custom_ad_float_settings', 'schedules_enabled')) {
            Schema::table('custom_ad_float_settings', function (Blueprint $table) {
                $table->boolean('schedules_enabled')->default(false)->after('schedules');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('custom_ad_float_settings') && Schema::hasColumn('custom_ad_float_settings', 'schedules_enabled')) {
            Schema::table('custom_ad_float_settings', function (Blueprint $table) {
                $table->dropColumn('schedules_enabled');
            });
        }
    }
};
