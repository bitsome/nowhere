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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('Operator')->after('profile_photo_path');
            $table->json('permissions')->nullable()->after('role');
            $table->string('status')->default('active')->after('permissions');
            $table->timestamp('last_login_at')->nullable()->after('status');
            $table->unsignedInteger('login_count')->default(0)->after('last_login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role',
                'permissions',
                'status',
                'last_login_at',
                'login_count',
            ]);
        });
    }
};
