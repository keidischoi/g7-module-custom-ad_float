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
            return;
        }

        if (! Schema::hasColumn('custom_ad_float_settings', 'link_open_mode')) {
            Schema::table('custom_ad_float_settings', function (Blueprint $table) {
                $table->string('link_open_mode', 20)->default('new_tab')->after('open_new_tab');
            });
        }

        $rows = DB::table('custom_ad_float_settings')->select('id', 'open_new_tab', 'link_open_mode')->get();
        foreach ($rows as $row) {
            $existing = is_string($row->link_open_mode ?? null) ? strtolower(trim((string) $row->link_open_mode)) : '';
            if (in_array($existing, ['same', 'new_tab', 'modal'], true)) {
                continue;
            }
            $raw = $row->open_new_tab ?? true;
            $on = ! ($raw === false || $raw === 0 || $raw === '0' || $raw === 'false');
            DB::table('custom_ad_float_settings')->where('id', $row->id)->update([
                'link_open_mode' => $on ? 'new_tab' : 'same',
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('custom_ad_float_settings')) {
            return;
        }
        if (Schema::hasColumn('custom_ad_float_settings', 'link_open_mode')) {
            Schema::table('custom_ad_float_settings', function (Blueprint $table) {
                $table->dropColumn('link_open_mode');
            });
        }
    }
};
