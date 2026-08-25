<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Presentation fields mirrored from the manifest by discovery, the same
     * way name and menu_order already are, so the launcher renders straight
     * from the registry without reading a manifest per tile.
     */
    public function up(): void
    {
        Schema::table('platform_apps', function (Blueprint $table) {
            $table->string('description')->nullable()->after('name');
            $table->string('icon', 16)->nullable()->after('description');
            $table->string('color', 32)->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('platform_apps', function (Blueprint $table) {
            $table->dropColumn(['description', 'icon', 'color']);
        });
    }
};
