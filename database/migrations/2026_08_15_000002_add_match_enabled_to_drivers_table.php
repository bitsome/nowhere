<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 자동 매칭(콜링) 시작/중지 플래그 — 기사가 매칭 탭에서 켜고 끈다
        Schema::table('drivers', function (Blueprint $table) {
            $table->boolean('match_enabled')->default(true)->after('status_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn('match_enabled');
        });
    }
};
