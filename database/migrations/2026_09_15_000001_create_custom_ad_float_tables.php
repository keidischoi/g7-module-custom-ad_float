<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('custom_ad_float_settings')) {
            Schema::create('custom_ad_float_settings', function (Blueprint $table) {
                $table->id();
                $table->boolean('enabled')->default(true);
                $table->boolean('home_only')->default(true);
                $table->string('position', 20)->default('right');
                $table->string('direction', 20)->default('horizontal');
                $table->unsignedInteger('interval_ms')->default(4000);
                $table->unsignedInteger('width_px')->default(180);
                $table->unsignedInteger('height_px')->default(180);
                $table->unsignedInteger('radius_px')->default(10);
                $table->unsignedInteger('offset_px')->default(24);
                $table->unsignedInteger('z_index')->default(9990);
                $table->unsignedInteger('max_items')->default(20);
                $table->boolean('autoplay')->default(true);
                $table->boolean('show_arrows')->default(true);
                $table->boolean('show_dots')->default(true);
                $table->boolean('show_close')->default(true);
                $table->string('close_cookie_key', 100)->default('g7_custom_ad_float_closed');
                $table->boolean('pause_on_hover')->default(true);
                $table->boolean('open_new_tab')->default(true);
                $table->string('mobile_mode', 20)->default('hide');
                $table->timestamp('start_at')->nullable();
                $table->timestamp('end_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('custom_ad_float_items')) {
            Schema::create('custom_ad_float_items', function (Blueprint $table) {
                $table->id();
                $table->string('title', 120)->nullable();
                $table->string('alt_text', 255)->nullable();
                $table->string('image_path', 1000);
                $table->string('target_url', 1000)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedInteger('display_seconds')->nullable();
                $table->boolean('enabled')->default(true);
                $table->timestamps();

                $table->index(['enabled', 'sort_order']);
            });
        }

        if (Schema::hasTable('custom_ad_float_settings') && DB::table('custom_ad_float_settings')->count() === 0) {
            DB::table('custom_ad_float_settings')->insert([
                'enabled' => true,
                'home_only' => true,
                'position' => 'right',
                'direction' => 'horizontal',
                'interval_ms' => 4000,
                'width_px' => 180,
                'height_px' => 180,
                'radius_px' => 10,
                'offset_px' => 24,
                'z_index' => 9990,
                'max_items' => 20,
                'autoplay' => true,
                'show_dots' => true,
                'show_close' => true,
                'close_cookie_key' => 'g7_custom_ad_float_closed',
                'pause_on_hover' => true,
                'open_new_tab' => true,
                'mobile_mode' => 'hide',
                'show_arrows' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_ad_float_items');
        Schema::dropIfExists('custom_ad_float_settings');
    }
};
