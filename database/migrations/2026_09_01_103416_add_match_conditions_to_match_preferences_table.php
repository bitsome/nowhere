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
        Schema::table('match_preferences', function (Blueprint $table) {
            // 서비스 유형(픽업/샌딩/랜딩) — 비우면 전체
            $table->string('service_type', 20)->nullable()->after('is_active');
            // 출발지/도착지 키워드 (마켓 출발지·도착지 필터와 동일한 표기)
            $table->string('origin', 100)->nullable()->after('service_type');
            $table->string('destination', 100)->nullable()->after('origin');
            // 매칭에 사용할 차량 — 비우면 전체, 지정하면 해당 차량으로만
            $table->foreignId('vehicle_id')->nullable()->after('destination')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_preferences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicle_id');
            $table->dropColumn(['service_type', 'origin', 'destination']);
        });
    }
};
