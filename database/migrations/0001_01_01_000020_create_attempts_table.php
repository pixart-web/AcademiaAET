<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt_number');
            $table->string('status', 16)->default('in_progress');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('submitted_at')->nullable();
            // Idempotency guard: a client retrying a submit after a dropped
            // response can safely resend the same key without double-counting.
            $table->string('submission_key')->nullable()->unique();
            $table->timestamps();

            $table->unique(['assignment_id', 'attempt_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempts');
    }
};
