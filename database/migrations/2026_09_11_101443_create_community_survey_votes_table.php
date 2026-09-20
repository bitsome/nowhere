<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 설문 투표 — 한 사람이 한 설문에 한 표만 갖도록 (post_id, user_id) 유니크로 강제한다.
     * 선택지 순번(option_id)은 community_posts.survey_options 의 인덱스를 가리킨다.
     */
    public function up(): void
    {
        Schema::create('community_survey_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('community_posts')->cascadeOnDelete();
            $table->unsignedSmallInteger('option_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_survey_votes');
    }
};
