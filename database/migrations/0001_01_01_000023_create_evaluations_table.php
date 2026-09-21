<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluated_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('score', 5, 2)->nullable();
            $table->json('criteria_scores')->nullable();
            // Shown to the child/guardian; distinct from clinical_notes, which
            // never leaves the professional portal.
            $table->text('shared_feedback')->nullable();
            $table->foreignId('clinical_note_id')->nullable()->constrained('clinical_notes')->nullOnDelete();
            $table->foreignId('supersedes_evaluation_id')->nullable()->constrained('evaluations')->nullOnDelete();
            $table->timestamp('evaluated_at')->useCurrent();
            $table->timestamps();

            $table->index(['attempt_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
