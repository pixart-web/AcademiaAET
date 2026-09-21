<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->foreignId('instruction_media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('response_type', 32);
            // Per-type shape: choice options + correct answer(s), drawing canvas
            // size, recording max duration, confirmation label, etc.
            $table->json('response_config')->nullable();
            $table->timestamps();

            $table->unique(['activity_version_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_steps');
    }
};
