<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('custom_ad_float_settings')) {
            return;
        }

        if (! Schema::hasColumn('custom_ad_float_settings', 'vertical_align')) {
            Schema::table('custom_ad_float_settings', function (Blueprint $table) {
                $table->string('vertical_align', 20)->default('middle')->after('offset_px');
            });
        }

        if (! Schema::hasColumn('custom_ad_float_settings', 'vertical_offset_px')) {
            Schema::table('custom_ad_float_settings', function (Blueprint $table) {
                $table->integer('vertical_offset_px')->default(24)->after('vertical_align');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('custom_ad_float_settings')) {
            return;
        }

        if (Schema::hasColumn('custom_ad_float_settings', 'vertical_offset_px')) {
            Schema::table('custom_ad_float_settings', function (Blueprint $table) {
                $table->dropColumn('vertical_offset_px');
            });
        }
        if (Schema::hasColumn('custom_ad_float_settings', 'vertical_align')) {
            Schema::table('custom_ad_float_settings', function (Blueprint $table) {
                $table->dropColumn('vertical_align');
            });
        }
    }
};
