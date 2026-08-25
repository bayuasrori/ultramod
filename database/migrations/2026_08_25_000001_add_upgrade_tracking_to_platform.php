<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_apps', function (Blueprint $table) {
            // `version` stays the *installed* version — the one whose migrations
            // and upgrade steps have actually run. `available_version` is what the
            // manifest on disk offers. Keeping them apart is what makes an
            // "upgrade available" state expressible at all.
            $table->string('available_version')->nullable()->after('version');
            $table->string('manifest_hash', 64)->nullable()->after('available_version');
            $table->timestamp('upgraded_at')->nullable()->after('installed_at');
            $table->text('last_upgrade_error')->nullable()->after('upgraded_at');
        });

        DB::table('platform_apps')->update(['available_version' => DB::raw('version')]);

        Schema::create('platform_app_upgrades', function (Blueprint $table) {
            $table->id();
            $table->string('app_id')->index();
            $table->string('from_version');
            $table->string('to_version');
            $table->string('step');
            $table->string('phase');
            $table->string('status');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('output')->nullable();
            $table->timestamps();

            // A step that already succeeded for a target version is never run
            // twice, so retrying a half-failed upgrade is safe.
            $table->unique(['app_id', 'to_version', 'step'], 'app_upgrade_step_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_app_upgrades');

        Schema::table('platform_apps', function (Blueprint $table) {
            $table->dropColumn(['available_version', 'manifest_hash', 'upgraded_at', 'last_upgrade_error']);
        });
    }
};
