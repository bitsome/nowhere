<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 관리자 개입(B-2) 컬럼 — 사용자 제재 상태, 운행 숨김·보류, 정산 보류.
 * - users.moderation_status: active(정상) | watch(주의) | restricted(운행 제한) | suspended(정지)
 * - orders.is_hidden: 마켓·추천 노출 제외(등록자·관리자에게는 보임)
 * - orders.admin_hold: 상태 진행 동결(관리자만 해제)
 * - settlements.hold_reason: 출금 신청 제외 대상(정산 보류)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('moderation_status', 20)->default('active')->after('status');
            $table->text('moderation_note')->nullable()->after('moderation_status');
            $table->foreignId('moderation_by')->nullable()->after('moderation_note')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('moderation_at')->nullable()->after('moderation_by');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_hidden')->default(false)->after('actual_revenue');
            $table->boolean('admin_hold')->default(false)->after('is_hidden');
            $table->string('admin_hold_reason', 255)->nullable()->after('admin_hold');
        });

        Schema::table('settlements', function (Blueprint $table) {
            $table->string('hold_reason', 255)->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('moderation_by');
            $table->dropColumn(['moderation_status', 'moderation_note', 'moderation_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['is_hidden', 'admin_hold', 'admin_hold_reason']);
        });

        Schema::table('settlements', function (Blueprint $table) {
            $table->dropColumn('hold_reason');
        });
    }
};
