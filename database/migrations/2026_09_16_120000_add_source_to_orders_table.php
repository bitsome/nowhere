<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 운행 등록 경로 — 위챗 파이프라인으로 들어온 운행과 앱에서 직접 등록한 운행을 구분한다.
     *
     * 유입은 원문(original_summary)을 싣고 들어오므로 그 값의 유무로 판정한다.
     * 관리자 화면의 '오늘 유입' 집계와 소유 계정 분리의 기준이 된다.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });
    }
};
