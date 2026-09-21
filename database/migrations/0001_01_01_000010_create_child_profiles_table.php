<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('preferred_name')->nullable();
            $table->date('birth_date');
            // Suggested by birth_date; a professional can override per module intro.
            $table->string('visual_experience', 16);
            $table->boolean('visual_experience_overridden')->default(false);
            $table->string('status', 16)->default('active');
            $table->text('care_notes')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_profiles');
    }
};
