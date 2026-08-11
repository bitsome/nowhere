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
            $table->string('pickup_location', 150)->nullable()->after('passenger_count');
            $table->string('dropoff_location', 150)->nullable()->after('pickup_location');
            $table->string('flight_number', 50)->nullable()->after('dropoff_location');
            $table->dateTime('scheduled_at')->nullable()->after('flight_number');
            $table->string('order_type', 30)->nullable()->after('scheduled_at');
            $table->unsignedInteger('estimated_duration_minutes')->nullable()->after('order_type');
            $table->decimal('distance_km', 8, 1)->nullable()->after('estimated_duration_minutes');
            $table->unsignedInteger('expected_revenue')->nullable()->after('distance_km');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'pickup_location',
                'dropoff_location',
                'flight_number',
                'scheduled_at',
                'order_type',
                'estimated_duration_minutes',
                'distance_km',
                'expected_revenue',
            ]);
        });
    }
};
