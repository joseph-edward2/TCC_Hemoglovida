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
        Schema::create('doador', function (Blueprint $table): void {
            $table->char('cpf_doador', 11)->primary();
            $table->string('nome_doador', 150);
            $table->string('email_doador', 150)->unique();
            $table->string('senha_doador', 255);
            $table->string('fone_doador', 20);
            $table->string('tipo_sanguineo', 3);
            $table->string('endereco_doador', 255)->nullable();
            $table->char('sexo_doador', 1)->nullable();
            $table->date('data_nasc_doador');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doador');
    }
};
