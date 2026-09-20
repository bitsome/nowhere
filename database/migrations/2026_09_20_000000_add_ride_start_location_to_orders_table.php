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
            // 운행 시작 시 기사 기기에서 1회 수신한 위치 — 분쟁 시 '실제로 어디서 출발했는가'의 근거.
            // 권한 거부·미지원·시간 초과면 null로 남고, 운행 시작 자체는 막지 않는다.
            $table->decimal('start_latitude', 10, 7)->nullable()->after('started_at');
            $table->decimal('start_longitude', 10, 7)->nullable()->after('start_latitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['start_latitude', 'start_longitude']);
        });
    }
};
