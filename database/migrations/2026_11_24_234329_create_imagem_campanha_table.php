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
        Schema::create('imagem_campanha', function (Blueprint $table): void {
            $table->unsignedBigInteger('id_evento')->primary();
            $table->foreign('id_evento')->references('id_evento')->on('evento')->cascadeOnDelete();
            $table->string('mime', 32);
            $table->mediumText('conteudo_base64');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imagem_campanha');
    }
};
