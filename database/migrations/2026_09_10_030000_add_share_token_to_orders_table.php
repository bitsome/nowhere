<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 운행 공유 토큰 — 등록자가 운행을 외부(카카오 오픈채팅·카페 등)에 링크로 공유할 때 쓰는 공개 식별자.
     *
     * 운행 id를 그대로 노출하면 순번 추측으로 다른 운행을 열람할 수 있으므로,
     * 추측 불가능한 임의 문자열을 따로 둔다. 토큰을 아는 사람만 그 운행을 볼 수 있다.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('share_token', 32)->nullable()->unique()->after('order_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('share_token');
        });
    }
};
