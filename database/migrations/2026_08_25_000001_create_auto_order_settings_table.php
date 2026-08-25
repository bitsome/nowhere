<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auto_order_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(false)->index();
            $table->unsignedInteger('min_count')->default(10);
            $table->unsignedInteger('max_count')->default(20);
            $table->unsignedInteger('owner_user_id')->nullable()->comment('자동 등록 계정 (null이면 관리자 기본 계정)');
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_order_settings');
    }
};
