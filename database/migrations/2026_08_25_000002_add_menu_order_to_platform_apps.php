<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_apps', function (Blueprint $table) {
            // Mirrored from the manifest by discovery, like name and provider,
            // so rendering the navbar never has to read a file.
            $table->integer('menu_order')->default(100)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('platform_apps', function (Blueprint $table) {
            $table->dropColumn('menu_order');
        });
    }
};
