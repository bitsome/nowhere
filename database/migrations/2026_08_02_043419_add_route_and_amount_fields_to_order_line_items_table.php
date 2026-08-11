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
        Schema::table('order_line_items', function (Blueprint $table) {
            $table->string('pickup_location', 150)->nullable()->after('location');
            $table->string('dropoff_location', 150)->nullable()->after('pickup_location');
            $table->string('flight_number', 50)->nullable()->after('dropoff_location');
            $table->unsignedInteger('amount_value')->nullable()->after('flight_number');
            $table->string('amount_text', 50)->nullable()->after('amount_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_line_items', function (Blueprint $table) {
            $table->dropColumn([
                'pickup_location',
                'dropoff_location',
                'flight_number',
                'amount_value',
                'amount_text',
            ]);
        });
    }
};
