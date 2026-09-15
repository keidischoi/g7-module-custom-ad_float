<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('custom_ad_float_settings') || ! Schema::hasColumn('custom_ad_float_settings', 'offset_px')) {
            return;
        }

        try {
            Schema::table('custom_ad_float_settings', function (Blueprint $table) {
                $table->integer('offset_px')->default(24)->change();
            });

            return;
        } catch (\Throwable $e) {
            // Native CHANGE may need doctrine/dbal; fall through to SQL.
        }

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE custom_ad_float_settings MODIFY offset_px INT NOT NULL DEFAULT 24');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE custom_ad_float_settings ALTER COLUMN offset_px TYPE INTEGER');
            DB::statement('ALTER TABLE custom_ad_float_settings ALTER COLUMN offset_px SET DEFAULT 24');
            DB::statement('ALTER TABLE custom_ad_float_settings ALTER COLUMN offset_px SET NOT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('custom_ad_float_settings') || ! Schema::hasColumn('custom_ad_float_settings', 'offset_px')) {
            return;
        }

        try {
            Schema::table('custom_ad_float_settings', function (Blueprint $table) {
                $table->unsignedInteger('offset_px')->default(24)->change();
            });
        } catch (\Throwable $e) {
            // keep signed if we cannot revert
        }
    }
};
