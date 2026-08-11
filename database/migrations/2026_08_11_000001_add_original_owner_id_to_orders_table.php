<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // claim(가져오기) 시 원 등록자를 기록 — 상호 리뷰의 대상 식별용
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('original_owner_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('original_owner_id');
        });
    }
};
