<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 관리자 감사 로그 — 정산·신고·사용자 제재·운행 개입·역할 변경 등
     * 관리자 행위를 '누가·언제·무엇을'로 남긴다 (분쟁·정산 대응 근거).
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->nullable()->index();
            $table->string('admin_name', 60)->nullable();
            $table->string('action', 60)->index();
            $table->string('message', 500);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
