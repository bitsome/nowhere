<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 기사 상태 (users 1:1) — 온라인/오프라인/휴식 + 온라인 시간 누적
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('offline'); // offline / online / on_trip / rest
            $table->timestamp('status_updated_at')->nullable();
            $table->unsignedInteger('online_seconds')->default(0); // 오늘 온라인 누적(초)
            $table->date('online_date')->nullable(); // 누적 기준일 (날짜 바뀌면 초기화)
            $table->timestamps();
        });

        // 기사 차량 (users 1:N)
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100); // 별칭 (예: 내 카니발)
            $table->string('type', 40)->nullable(); // 차종 (주문 vehicle_type과 매칭)
            $table->string('license_plate', 30)->nullable();
            $table->string('color', 30)->nullable();
            $table->unsignedTinyInteger('capacity')->default(0); // 승차정원
            $table->unsignedTinyInteger('luggage_capacity')->default(0); // 짐 공간
            $table->date('insurance_expires_at')->nullable(); // 보험 만료
            $table->string('photo_path')->nullable(); // 차량 사진
            $table->boolean('is_default')->default(false); // 기본 차량
            $table->boolean('is_verified')->default(false); // 관리자 검증
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('drivers');
    }
};
