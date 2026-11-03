<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Banco de itens do rastreio — conteúdo clínico da clínica (importado,
        // nunca escrito no código); is_demo marca os itens fictícios de exemplo.
        Schema::create('screening_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('age_block', 8);
            $table->string('kind', 16);
            $table->unsignedSmallInteger('position');
            // Texto que a criança ouve (compreensão / nomeação / repetição) ou a palavra-alvo (fala).
            $table->text('prompt');
            // Fala: palavra-alvo e fonema-alvo (chave de config/screening.php).
            $table->string('target_word')->nullable();
            $table->string('phoneme', 16)->nullable();
            // Compreensão: [{label, media_asset_id?, correct}] — nunca enviado ao cliente com `correct`.
            $table->json('options')->nullable();
            // Resposta esperada (nomeação) / frase-alvo (repetição) — só para o avaliador.
            $table->string('expected_answer')->nullable();
            $table->foreignId('image_media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();

            $table->index(['organization_id', 'age_block', 'kind', 'position']);
        });

        Schema::create('screening_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conducted_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('institution_name');
            $table->string('child_name');
            $table->date('birth_date');
            $table->string('gender', 24);
            $table->string('guardian_name');
            $table->string('guardian_email');
            $table->string('guardian_phone', 40);
            $table->date('screened_on');
            $table->unsignedSmallInteger('age_months');
            $table->string('age_block', 8);
            $table->string('status', 16)->default('in_progress');
            $table->timestamp('consent_confirmed_at');
            $table->timestamp('completed_at')->nullable();
            $table->json('result')->nullable();
            $table->string('report_path')->nullable();
            $table->timestamp('report_guardian_sent_at')->nullable();
            $table->timestamp('report_clinic_sent_at')->nullable();
            $table->string('report_error')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('screening_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('screening_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('screening_item_id')->constrained()->cascadeOnDelete();
            $table->string('outcome', 16);
            $table->unsignedTinyInteger('points')->nullable();
            // Substituição: som produzido pela criança.
            $table->string('produced_sound', 32)->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['screening_session_id', 'screening_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screening_responses');
        Schema::dropIfExists('screening_sessions');
        Schema::dropIfExists('screening_items');
    }
};
