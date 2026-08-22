<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Settings
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('app_id')->nullable()->index(); // null for global platform settings
            $table->string('key')->index();
            $table->json('value')->nullable();
            $table->timestamps();
            
            $table->unique(['app_id', 'key']);
        });

        // 2. Audit / Activity Logs
        Schema::create('platform_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable()->index(); // user id
            $table->string('action')->index(); // e.g. "created", "login", "app.enabled"
            $table->nullableMorphs('target'); // target_type, target_id
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // 3. Files
        Schema::create('platform_files', function (Blueprint $table) {
            $table->id();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedBigInteger('owner_id')->nullable()->index();
            $table->nullableMorphs('attachment');
            $table->timestamps();
        });
        
        // 4. Webhooks
        Schema::create('platform_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('app_id')->nullable()->index();
            $table->string('event');
            $table->string('url');
            $table->string('secret')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_webhooks');
        Schema::dropIfExists('platform_files');
        Schema::dropIfExists('platform_audit_logs');
        Schema::dropIfExists('platform_settings');
    }
};
