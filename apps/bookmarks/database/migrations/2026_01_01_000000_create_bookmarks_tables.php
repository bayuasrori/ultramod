<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('collection')->nullable()->index();
            $table->boolean('is_favorite')->default(false)->index();
            $table->string('favicon_url')->nullable();
            $table->string('site_name')->nullable();
            $table->timestamp('metadata_fetched_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bookmark_tag', function (Blueprint $table) {
            $table->foreignId('bookmark_id')->constrained()->cascadeOnDelete();
            $table->string('tag');
            $table->unique(['bookmark_id', 'tag']);
            $table->index('tag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookmark_tag');
        Schema::dropIfExists('bookmarks');
    }
};
