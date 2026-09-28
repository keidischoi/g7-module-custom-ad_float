<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 마일리지 적립 원장 (배너 클릭 적립 등). 같은 대상은 1번만 적립.
     */
    public function up(): void
    {
        if (Schema::hasTable('custom_ad_float_mileage_rewards')) {
            return;
        }

        Schema::create('custom_ad_float_mileage_rewards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('action', 40);
            $table->string('target_key', 100);
            $table->integer('amount')->default(0);
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->timestamp('reclaimed_at')->nullable();
            $table->integer('reclaimed_amount')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'action', 'target_key'], 'caf_mr_user_action_target_unique');
            $table->index(['user_id', 'action', 'created_at'], 'caf_mr_user_action_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_ad_float_mileage_rewards');
    }
};
