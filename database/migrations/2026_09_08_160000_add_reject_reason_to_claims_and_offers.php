<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 거절 사유 — 가져오기 신청(order_claims)·요금 제안(order_offers) 거절 시
     * 등록자가 남긴 사유를 기록해 상대 기사에게 전달·분쟁 근거로 쓴다 (선택 입력).
     */
    public function up(): void
    {
        Schema::table('order_claims', function (Blueprint $table) {
            $table->string('reject_reason', 500)->nullable()->after('status');
        });

        Schema::table('order_offers', function (Blueprint $table) {
            $table->string('reject_reason', 500)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('order_offers', function (Blueprint $table) {
            $table->dropColumn('reject_reason');
        });

        Schema::table('order_claims', function (Blueprint $table) {
            $table->dropColumn('reject_reason');
        });
    }
};
