<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('instructions_override')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->unsignedTinyInteger('max_attempts')->nullable();
            $table->string('status', 16)->default('assigned');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['child_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
