<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardian_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('relationship_type', 32);
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->unique(['child_profile_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_relationships');
    }
};
