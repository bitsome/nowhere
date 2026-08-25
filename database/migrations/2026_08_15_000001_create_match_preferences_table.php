<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 기사 자동 매칭 설정 — 시간대/요일/출발지역/최소수익 조건
        Schema::create('match_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100); // 설정 이름 (예: 아침 공항 콜)
            $table->string('start_time', 5)->nullable(); // 시간대 시작 HH:MM
            $table->string('end_time', 5)->nullable(); // 시간대 종료 HH:MM
            $table->json('days')->nullable(); // 요일 [1(월)..7(일)], null=전체
            $table->string('area', 100)->nullable(); // 출발지 키워드 (예: 인천공항)
            $table->unsignedInteger('min_revenue')->default(0); // 최소 수익(원)
            $table->boolean('is_active')->default(true); // 매칭 활성화
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_preferences');
    }
};
