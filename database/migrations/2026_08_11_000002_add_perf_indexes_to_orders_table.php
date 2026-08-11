<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 목록 정렬·날짜 필터에 자주 쓰이는 컬럼 인덱스 (마켓/내 오더 조회 성능)
        Schema::table('orders', function (Blueprint $table) {
            $table->index('service_date');
            $table->index('claimed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['service_date']);
            $table->dropIndex(['claimed_at']);
        });
    }
};
