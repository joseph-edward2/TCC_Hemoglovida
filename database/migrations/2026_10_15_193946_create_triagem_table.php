<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('triagem', function (Blueprint $table) {
            $table->id();
            $table->enum('status_triagem', ['aprovado', 'reprovado']);
            $table->datetime('data_hora_triagem');
            $table->string('observacao_triagem', 255);
            $table->timestamps();
            
            $table->foreignId('login_funcionario')->constrained('funcionario')->onDelete('cascade')->onUpdate('cascade');
            $table->foreignId('id_doacao')->constrained('doacao')->onDelete('cascade')->onUpdate('cascade');

            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('triagem');
    }
};
