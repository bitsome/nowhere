<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 신고/분쟁 접수 — 운행·사용자(기사/등록자)·채팅을 대상으로 한 신고.
 * 처리 흐름(접수→확인→조사→처리→완료)과 관리자 메모·처리자를 기록해 분쟁 대응 근거로 삼는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            // 신고한 사용자
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            // 신고 대상 — order(운행) | user(기사/등록자) | chat(채팅)
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id');
            // 신고 대상 사용자(조사·제재 대상) — user 대상이면 그 자신, chat이면 상대방. 운행은 상황 따라 없을 수 있음
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            // 신고 유형 — fee | cancel | no_show | address | time | chat | service | other
            $table->string('category', 30);
            $table->text('reason');
            // 처리 흐름 — pending(접수) → reviewing(확인) → investigating(조사) → handled(처리) → completed(완료)
            $table->string('status', 20)->default('pending');
            // 관리자 처리 메모(처리 결과·중재 내용)
            $table->text('note')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
