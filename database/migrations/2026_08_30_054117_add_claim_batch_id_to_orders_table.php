<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 일괄 가져오기 요청 그룹 키 — 추천1 체인에서 한 번에 보낸 요청끼리
     * 내 마켓 요청 탭에서 한 그룹으로 묶어 보여주기 위한 식별자.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('claim_batch_id', 40)
                ->nullable()
                ->after('claim_lock_until')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['claim_batch_id']);
            $table->dropColumn('claim_batch_id');
        });
    }
};
