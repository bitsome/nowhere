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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('request_label')->nullable()->after('order_number');
            $table->string('service_date')->nullable()->after('request_label');
            $table->string('service_time')->nullable()->after('service_date');
            $table->string('group_type')->nullable()->after('service_time');
            $table->string('vehicle_type')->nullable()->after('group_type');
            $table->string('service_type')->nullable()->after('vehicle_type');
            $table->unsignedInteger('luggage_count')->nullable()->after('passenger_count');
            $table->string('amount_text')->nullable()->after('luggage_count');
            $table->unsignedInteger('amount_value')->nullable()->after('amount_text');
            $table->json('extra_options')->nullable()->after('amount_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'request_label',
                'service_date',
                'service_time',
                'group_type',
                'vehicle_type',
                'service_type',
                'luggage_count',
                'amount_text',
                'amount_value',
                'extra_options',
            ]);
        });
    }
};
