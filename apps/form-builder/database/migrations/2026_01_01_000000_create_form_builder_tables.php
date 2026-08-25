<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_builder_forms', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('success_message')->nullable();
            $table->boolean('is_published')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('form_builder_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('form_builder_forms')->cascadeOnDelete();
            $table->string('label');
            $table->string('key');
            $table->string('type')->default('text');
            $table->string('placeholder')->nullable();
            $table->string('help')->nullable();
            // One choice per line; only meaningful for select and radio.
            $table->text('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['form_id', 'key']);
            $table->index(['form_id', 'position']);
        });

        Schema::create('form_builder_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('form_builder_forms')->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            // Answers are keyed by field key, so a submission survives a field
            // being renamed, reordered or removed from the form afterwards.
            $table->json('answers');
            $table->timestamps();

            $table->index(['form_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_builder_submissions');
        Schema::dropIfExists('form_builder_fields');
        Schema::dropIfExists('form_builder_forms');
    }
};
