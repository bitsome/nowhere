<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // 운행중 세부 단계별 기록 시각 — { 단계: ISO시각 } 맵. 스테퍼 각 단계 아래에 추적 시간을 표시한다.
            $table->json('ride_step_times')->nullable()->after('ride_step');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('ride_step_times');
        });
    }
};
