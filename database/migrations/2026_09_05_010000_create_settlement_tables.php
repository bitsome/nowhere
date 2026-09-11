<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 기사 계좌 — 계좌 1개(기사별 유일). 출금 신청 시 스냅샷으로 복사한다.
        Schema::create('user_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('bank_name', 60);
            $table->string('account_number', 60);
            $table->string('account_holder', 60);
            $table->timestamps();
        });

        // 출금 신청 — 기사가 정산(미지급) 금액을 한 건으로 신청 → 관리자 확인·지급.
        // settlements.payout_id가 payout_requests를 참조하므로 정산 원장보다 먼저 생성한다.
        Schema::create('payout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->string('bank_name', 60);
            $table->string('account_number', 60);
            $table->string('account_holder', 60);
            $table->string('status', 20)->default('pending');        // pending | paid | rejected
            $table->string('note', 200)->nullable();                 // 거절 사유 등
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'status']);
            $table->index('status');
        });

        // 정산 원장 — 정산(settled)된 운행별 금액 확정 기록 (운행금액·수수료·실지급액·지급일)
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('registrant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('gross_amount')->default(0);   // 정산 대상 운행금액
            $table->unsignedInteger('fee_amount')->default(0);      // 플랫폼 수수료
            $table->unsignedInteger('net_amount')->default(0);      // 실지급액 (운행금액 - 수수료)
            $table->string('status', 20)->default('pending');       // pending(출금 대기) | paid(지급 완료)
            $table->foreignId('payout_id')->nullable()->constrained('payout_requests')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();               // 지급일
            $table->timestamps();

            $table->index('driver_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
        Schema::dropIfExists('payout_requests');
        Schema::dropIfExists('user_bank_accounts');
    }
};
