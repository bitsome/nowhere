<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 오더 취소 사유 컬럼 추가.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('cancel_reason')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('cancel_reason');
        });
    }
};
