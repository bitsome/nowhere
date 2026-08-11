<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewee_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('content', 500);
            $table->timestamps();

            // 오더당 작성자는 1회만 리뷰 가능
            $table->unique(['order_id', 'reviewer_id']);

            $table->index('reviewee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
