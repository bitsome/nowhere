<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_vehicle_verified')->default(false)->after('status');
            $table->boolean('is_license_verified')->default(false)->after('is_vehicle_verified');
            $table->boolean('is_vip')->default(false)->after('is_license_verified');
            $table->string('vehicle_info')->nullable()->after('is_vip');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_vehicle_verified',
                'is_license_verified',
                'is_vip',
                'vehicle_info',
            ]);
        });
    }
};
