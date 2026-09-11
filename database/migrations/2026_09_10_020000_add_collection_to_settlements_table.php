<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 정산 원장에 등록자 대금 수금 상태를 추가한다.
     * 등록자가 플랫폼에 운행금액(gross)을 입금하면 관리자가 '입금 확인' 처리한다.
     * 수금이 완료된 정산만 기사 출금 재원이 되므로, 기사 출금 가능 금액은 수금 완료분만 포함한다.
     */
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->string('collection_status', 20)->default('pending')->after('status'); // pending(입금 대기) | paid(입금 확인)
            $table->timestamp('collected_at')->nullable()->after('collection_status');
            $table->foreignId('collected_by')->nullable()->after('collected_at')->constrained('users')->nullOnDelete();
            $table->string('collection_note', 200)->nullable()->after('collected_by');

            $table->index('collection_status');
        });
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropColumn(['collection_status', 'collected_at', 'collected_by', 'collection_note']);
        });
    }
};
