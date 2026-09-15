<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('custom_ad_float_settings') || Schema::hasColumn('custom_ad_float_settings', 'placements')) {
            return;
        }

        Schema::table('custom_ad_float_settings', function (Blueprint $table) {
            $table->json('placements')->nullable()->after('schedules_enabled');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('custom_ad_float_settings') || ! Schema::hasColumn('custom_ad_float_settings', 'placements')) {
            return;
        }

        Schema::table('custom_ad_float_settings', function (Blueprint $table) {
            $table->dropColumn('placements');
        });
    }
};
