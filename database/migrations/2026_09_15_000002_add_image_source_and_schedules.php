<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('custom_ad_float_items') && ! Schema::hasColumn('custom_ad_float_items', 'image_source')) {
            Schema::table('custom_ad_float_items', function (Blueprint $table) {
                $table->string('image_source', 20)->default('url')->after('image_path');
            });

            $items = DB::table('custom_ad_float_items')->select('id', 'image_path')->get();
            foreach ($items as $item) {
                $path = (string) $item->image_path;
                $source = (preg_match('#^https?://#i', $path) || str_starts_with($path, '//') || str_starts_with($path, '/'))
                    ? 'url'
                    : 'upload';
                DB::table('custom_ad_float_items')->where('id', $item->id)->update(['image_source' => $source]);
            }
        }

        if (Schema::hasTable('custom_ad_float_settings') && ! Schema::hasColumn('custom_ad_float_settings', 'schedules')) {
            Schema::table('custom_ad_float_settings', function (Blueprint $table) {
                $table->json('schedules')->nullable()->after('end_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('custom_ad_float_items') && Schema::hasColumn('custom_ad_float_items', 'image_source')) {
            Schema::table('custom_ad_float_items', function (Blueprint $table) {
                $table->dropColumn('image_source');
            });
        }
        if (Schema::hasTable('custom_ad_float_settings') && Schema::hasColumn('custom_ad_float_settings', 'schedules')) {
            Schema::table('custom_ad_float_settings', function (Blueprint $table) {
                $table->dropColumn('schedules');
            });
        }
    }
};
