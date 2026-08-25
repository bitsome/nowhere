<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 누락된 성능 인덱스 추가 — 조회/필터/정렬 핵심 경로.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 운행 — 내 운행/가져온 운행/소유자 통계/시간 겹침 검사 등
        Schema::table('orders', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('claimant_user_id');
            $table->index(['status', 'service_date']);
            $table->index(['user_id', 'status']);
            $table->index(['service_date', 'service_time']);
        });

        // 대화 — 최신 메시지순 목록
        Schema::table('conversations', function (Blueprint $table) {
            $table->index('last_message_at');
        });

        // 메시지 — 안 읽은 수/읽음 처리 (대화별 상대 필터)
        Schema::table('messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'user_id']);
        });

        // 매칭 설정 — 활성 설정 조회
        Schema::table('match_preferences', function (Blueprint $table) {
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['claimant_user_id']);
            $table->dropIndex(['status', 'service_date']);
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['service_date', 'service_time']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['last_message_at']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'user_id']);
        });

        Schema::table('match_preferences', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_active']);
        });
    }
};
