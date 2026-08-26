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
            // 운행 시작/완료 시각 — 운행중 전이 시 시작, 완료 전이 시 완료 시각을 기록한다
            $table->timestamp('started_at')->nullable()->after('claimed_at');
            $table->timestamp('completed_at')->nullable()->after('started_at');
            // 실제 수익 — 완료 전이 시 입력된 실제 정산 금액 (기대 금액과 별개로 기록)
            $table->unsignedInteger('actual_revenue')->nullable()->after('expected_revenue');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'completed_at', 'actual_revenue']);
        });
    }
};
