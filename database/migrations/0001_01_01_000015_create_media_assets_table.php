<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 32);
            $table->string('path');
            $table->string('mime_type', 128);
            $table->string('kind', 16);
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->text('alt_text')->nullable();
            $table->text('transcript')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->string('status', 16)->default('active');
            $table->string('license')->nullable();
            $table->string('attribution')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'kind', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
