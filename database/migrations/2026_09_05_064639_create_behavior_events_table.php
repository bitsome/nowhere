<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 행동(이벤트) 로그 — 개인화 추천의 원료가 되는 기사 행동과
     * 추천·마켓 노출/클릭을 한 테이블에 담는다.
     * 노출·클릭은 앱에서 일괄 전송되고, 신청/철회/거절/완료/취소는
     * claim·상태 전이 서비스가 확정 시점에 기록한다.
     */
    public function up(): void
    {
        Schema::create('behavior_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // 노출·클릭은 오래된 목록에서 온 order_id일 수 있어 외래키를 걸지 않는다 (분석 데이터 보존 우선)
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('event', 40);
            $table->json('meta')->nullable();
            $table->timestamps();

            // 기사별 행동 스트림·운행별 추이 조회용 인덱스
            $table->index(['user_id', 'event']);
            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('behavior_events');
    }
};
