<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('custom_ad_float_stats')) {
            return;
        }

        Schema::create('custom_ad_float_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('page_path', 255);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->date('stat_date');
            $table->timestamps();

            $table->unique(['item_id', 'page_path', 'stat_date'], 'caf_stats_item_path_date_uq');
            $table->index('item_id');
            $table->index('page_path');
            $table->index('stat_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_ad_float_stats');
    }
};
