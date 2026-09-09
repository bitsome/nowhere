<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 차량·면허 증빙 심사 요청 — 사용자가 증빙 사진을 올리면 관리자가 승인/거절한다 (B-3).
     * 승인되면 users.is_vehicle_verified / is_license_verified에 반영된다.
     */
    public function up(): void
    {
        Schema::create('verification_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // vehicle | license
            $table->string('image_path'); // 증빙 사진 (public disk)
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->string('note')->nullable(); // 신청자 메모 (선택)
            $table->text('review_note')->nullable(); // 관리자 심사 메모·거절 사유
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_requests');
    }
};
