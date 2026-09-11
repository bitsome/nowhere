<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 정산 원장에 정산 시점의 플랫폼 수수료율 스냅샷을 남긴다.
     * 운영 중 요율(settlement.fee_rate)을 바꿔도 이미 확정된 정산 금액은 그대로 유지되도록 하기 위함이다.
     */
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->decimal('fee_rate', 6, 4)->nullable()->after('fee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropColumn('fee_rate');
        });
    }
};
