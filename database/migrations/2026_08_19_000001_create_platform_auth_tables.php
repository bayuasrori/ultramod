<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_super_admin')->default(false);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('platform_role_id')->nullable()->after('remember_token')
                ->constrained('platform_roles')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('platform_role_id');
        });

        Schema::create('platform_role_permissions', function (Blueprint $table) {
            $table->foreignId('platform_role_id')->constrained('platform_roles')->cascadeOnDelete();
            $table->foreignId('platform_app_permission_id')->constrained('platform_app_permissions')->cascadeOnDelete();
            $table->primary(['platform_role_id', 'platform_app_permission_id'], 'role_permission_primary');
        });

        Schema::create('platform_login_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('email')->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->boolean('successful')->default(false);
            $table->timestamp('login_at');
            $table->timestamp('logout_at')->nullable();
            $table->index(['user_id', 'login_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_login_history');
        Schema::dropIfExists('platform_role_permissions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('platform_role_id');
            $table->dropColumn('is_active');
        });

        Schema::dropIfExists('platform_roles');
    }
};
