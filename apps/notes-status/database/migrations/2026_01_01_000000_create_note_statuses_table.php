<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 30)->default('secondary');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('note_status_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('note_id')->unique();
            $table->unsignedBigInteger('note_status_id');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('note_status_id')->references('id')->on('note_statuses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_status_assignments');
        Schema::dropIfExists('note_statuses');
    }
};
