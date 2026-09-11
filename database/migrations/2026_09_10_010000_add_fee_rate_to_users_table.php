<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 등록자(업체)별 개별 수수료율 override.
     * 값이 있으면 전역 정책(`settlement.fee_rate`) 대신 이 요율로 정산하고, null이면 전역 정책을 따른다.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('fee_rate', 6, 4)->nullable()->after('company_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('fee_rate');
        });
    }
};
