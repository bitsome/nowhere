<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 운행신청 재신청 제한 — 거절·철회된 드라이버가 30초 이내 다시 신청하지 못하게 한다.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('claim_lock_driver_id')
                ->nullable()
                ->after('claimant_user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('claim_lock_until')
                ->nullable()
                ->after('claim_lock_driver_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('claim_lock_driver_id');
            $table->dropColumn('claim_lock_until');
        });
    }
};
