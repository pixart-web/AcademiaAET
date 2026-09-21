<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 64)->nullable();
            $table->string('area', 64)->nullable();
            $table->string('difficulty', 16)->nullable();
            $table->string('status', 16)->default('draft');
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            // Points at the currently-published ActivityVersion; the version an
            // Assignment references never moves even after this pointer changes.
            $table->foreignId('current_version_id')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
