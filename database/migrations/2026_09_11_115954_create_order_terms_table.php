<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 운행 용어 사전 — 사전에 없던 중국어 표기를 모아 두고 관리자가 한국어로 매핑한다.
     *
     * 미매핑(status=pending)으로 쌓이다가, 관리자가 한국어를 지정하면(status=mapped)
     * 그때부터 저장 파이프라인이 이 값을 사전으로 함께 쓴다.
     */
    public function up(): void
    {
        Schema::create('order_terms', function (Blueprint $table) {
            $table->id();
            $table->string('field', 40);
            $table->string('term', 200);
            $table->string('mapped_to', 200)->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignId('mapped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('mapped_at')->nullable();
            $table->timestamps();

            $table->unique(['field', 'term']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_terms');
    }
};
