<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Q-1 정리 — 레거시 boards 게시판 테이블 제거.
     * 커뮤니티(CommunityPost)·고객지원(SupportPost) 체계로 대체되어
     * 모델·라우트·사용처가 모두 사라진 상태다. (신규 설치에서는 생성되지 않는다)
     */
    public function up(): void
    {
        Schema::dropIfExists('boards');
    }

    /**
     * Reverse the migrations.
     *
     * 원본 생성 마이그레이션(2026_08_01_100017)을 삭제했으므로 롤백 시 동일 스키마를 복원한다.
     */
    public function down(): void
    {
        Schema::create('boards', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index();
            $table->string('title');
            $table->longText('content');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('published')->index();
            $table->boolean('is_notice')->default(false);
            $table->boolean('is_private')->default(false);
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();
        });
    }
};
