<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('custom_ad_float_items') || Schema::hasColumn('custom_ad_float_items', 'carousel_group')) {
            return;
        }

        Schema::table('custom_ad_float_items', function (Blueprint $table) {
            $table->string('carousel_group', 36)->nullable()->after('enabled');
            $table->index(['carousel_group', 'sort_order']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('custom_ad_float_items') || ! Schema::hasColumn('custom_ad_float_items', 'carousel_group')) {
            return;
        }

        Schema::table('custom_ad_float_items', function (Blueprint $table) {
            $table->dropColumn('carousel_group');
        });
    }
};
