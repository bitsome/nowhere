<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 등록자(업체) 본인 인증 배지 — 사업자등록증(business)·대표 계좌(account) 심사 결과 (Q-4).
     * 기사 차량/면허 배지(2026_08_09)와 동일하게 사용자 프로필·프런트 인증 상태에 쓰인다.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_business_verified')->default(false)->after('is_license_verified');
            $table->boolean('is_account_verified')->default(false)->after('is_business_verified');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_business_verified',
                'is_account_verified',
            ]);
        });
    }
};
