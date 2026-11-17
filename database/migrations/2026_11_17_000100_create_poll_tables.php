<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Uma linha só: o estado ao vivo da enquete, que o painel controla e o
        // telão e os celulares leem a cada poll.
        Schema::create('poll_states', function (Blueprint $table) {
            $table->id();
            $table->boolean('accepting')->default(true);
            // invite = slide com as perguntas e o QR; question = resultado de
            // uma pergunta; all = as quatro lado a lado
            $table->string('screen', 16)->default('invite');
            $table->unsignedBigInteger('screen_question_id')->nullable();
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('position')->unique();
            $table->text('prompt');
            // a pergunta 4 lista as alternativas no próprio card do telão
            $table->boolean('inline_options')->default(false);
            // o resultado já foi ao telão: a partir daí aparece também nos celulares
            $table->timestamp('revealed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('label');
            $table->timestamps();

            $table->unique(['question_id', 'position']);
        });

        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            // gerado no celular e guardado no localStorage: anônimo, mas um voto
            // por aparelho e por pergunta
            $table->uuid('respondent');
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('option_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['respondent', 'question_id']);
            $table->index(['question_id', 'option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answers');
        Schema::dropIfExists('options');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('poll_states');
    }
};
