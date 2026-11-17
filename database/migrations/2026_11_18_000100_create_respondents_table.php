<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quem respondeu. O `token` é o mesmo UUID que o celular manda no
        // header X-Respondent e que as respostas já guardam em `answers.respondent`.
        Schema::create('respondents', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->string('name', 80);
            // e-mail OU WhatsApp: um dos dois basta, e cada um identifica uma pessoa
            $table->string('email', 120)->nullable()->unique();
            $table->string('phone', 11)->nullable()->unique();
            $table->string('hotel', 120);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respondents');
    }
};
