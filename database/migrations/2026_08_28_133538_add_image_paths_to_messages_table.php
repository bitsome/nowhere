<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // 한 개 말풍선에 여러 장 이미지를 담을 수 있도록 경로 배열을 추가한다.
            // 기존 단일 이미지(image_path) 메시지는 image_path가 유지되고 image_paths는 null이다.
            $table->json('image_paths')->nullable()->after('image_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('image_paths');
        });
    }
};
