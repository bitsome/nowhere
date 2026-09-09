<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 고객지원 공지·FAQ — 관리자(Admin/Super Admin)가 작성하는 도움말 게시물 (B-4).
     */
    public function up(): void
    {
        Schema::create('support_posts', function (Blueprint $table) {
            $table->id();
            $table->string('kind'); // notice | faq
            $table->string('title');
            $table->text('body');
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['kind', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_posts');
    }
};
