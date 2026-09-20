<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 커뮤니티 확장 — 설문조사(선택지·마감)와 여행지 맛집 카드(장소 정보).
     *
     * 설문 선택지는 문항이 만들어질 때 확정되고 투표가 그 순번(option_id)을 가리키므로
     * 별도 선택지 테이블을 두지 않고 JSON 목록으로 보관한다(수정 화면에서 선택지를 바꾸지 않는다).
     */
    public function up(): void
    {
        Schema::table('community_posts', function (Blueprint $table) {
            // 설문조사 — 선택지 목록(JSON 배열)과 마감 시각. 비어 있으면 일반 글
            $table->json('survey_options')->nullable()->after('category');
            $table->timestamp('survey_closes_at')->nullable()->after('survey_options');

            // 여행지 맛집 카드 — 구조화된 장소 정보
            $table->string('place_name')->nullable()->after('survey_closes_at');
            $table->string('place_region', 40)->nullable()->after('place_name');
            $table->string('place_address', 150)->nullable()->after('place_region');
            $table->string('place_map_url', 500)->nullable()->after('place_address');
        });
    }

    public function down(): void
    {
        Schema::table('community_posts', function (Blueprint $table) {
            $table->dropColumn([
                'survey_options',
                'survey_closes_at',
                'place_name',
                'place_region',
                'place_address',
                'place_map_url',
            ]);
        });
    }
};
