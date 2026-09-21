<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('step_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_step_id')->constrained()->cascadeOnDelete();
            // Choice/text answers, drawing stroke data, or a reference to the
            // media_asset_id holding a voice/video recording.
            $table->json('value')->nullable();
            $table->foreignId('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->boolean('is_correct')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['attempt_id', 'activity_step_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('step_responses');
    }
};
