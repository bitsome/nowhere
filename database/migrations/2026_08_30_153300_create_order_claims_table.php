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
        // 가져오기 신청 목록 — 한 운행에 여러 드라이버가 동시에 신청할 수 있다.
        // 승인/거절은 이 테이블의 개별 신청 건(claim) 단위로 처리한다.
        Schema::create('order_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending')->index(); // pending/approved/rejected/withdrawn
            $table->string('batch_id', 64)->nullable()->index();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['driver_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_claims');
    }
};
