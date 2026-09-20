<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 운행 유입 원본 — 외부(위챗 모니터 등)가 보낸 payload 를 변환 전 그대로 보관한다.
     *
     * 변환·검증에 실패해도 원본은 남으므로 재처리와 분쟁 대응이 가능하다.
     */
    public function up(): void
    {
        Schema::create('order_ingestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('endpoint', 60);
            $table->json('payload');
            $table->string('status', 20)->default('received');
            $table->json('order_ids')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_ingestions');
    }
};
