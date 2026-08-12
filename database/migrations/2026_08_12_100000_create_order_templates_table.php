<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 오더 등록 템플릿 — 자주 쓰는 노선·차량·가격을 저장해 원클릭 등록.
     */
    public function up(): void
    {
        Schema::create('order_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('service_type', 20)->nullable();
            $table->string('vehicle_type', 50)->nullable();
            $table->string('pickup_location', 255)->nullable();
            $table->string('dropoff_location', 255)->nullable();
            $table->unsignedInteger('passenger_count')->nullable();
            $table->unsignedInteger('expected_revenue')->nullable();
            $table->string('flight_number', 20)->nullable();
            $table->string('reservation_company', 100)->nullable();
            $table->text('memo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_templates');
    }
};
