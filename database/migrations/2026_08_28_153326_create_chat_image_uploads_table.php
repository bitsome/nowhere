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
        // 채팅 이미지 업로드 소유권 — 전송 전에 올린 새 사진(아직 메시지로 저장되지 않은 지문)을
        // 소유자별로 기록해, 같은 사용자가 곧바로 묶음 전송할 때 검증을 통과시킨다.
        Schema::create('chat_image_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('image_path', 255);
            $table->timestamps();

            $table->unique(['user_id', 'image_path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_image_uploads');
    }
};
