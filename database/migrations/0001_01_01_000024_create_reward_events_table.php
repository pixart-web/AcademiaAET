<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attempt_id')->nullable()->constrained('attempts')->nullOnDelete();
            $table->string('type', 32);
            $table->unsignedInteger('points')->default(0);
            // Deterministic per (attempt, type) so a resubmitted attempt can
            // never mint a second reward for the same event.
            $table->string('dedupe_key')->unique();
            $table->timestamp('awarded_at')->useCurrent();
            $table->timestamps();

            $table->index(['child_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_events');
    }
};
