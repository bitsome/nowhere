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
        Schema::create('order_offers', function (Blueprint $table) {
            $table->id();
            // 제안 대상 운행 — 운행 삭제 시 제안도 함께 정리
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // 제안한 기사 — 계정 삭제 시 제안 정리
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('amount')->comment('제안 운임 (원)');
            $table->string('message', 500)->nullable()->comment('제안 메모');
            $table->string('status')->default('pending')->comment('pending/accepted/rejected/cancelled');
            $table->timestamps();

            // 운행별 대기 제안 조회가 잦다 (등록자가 여러 제안 비교)
            $table->index(['order_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_offers');
    }
};
