<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 운행 즐겨찾기(찜) — 기사가 마음에 드는 운행을 임시 보관한다.
     * 운행이 마켓에서 빠지면(다른 기사가 가져감·취소·숨김) 알림 후 자동 정리된다.
     */
    public function up(): void
    {
        Schema::create('order_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_favorites');
    }
};
