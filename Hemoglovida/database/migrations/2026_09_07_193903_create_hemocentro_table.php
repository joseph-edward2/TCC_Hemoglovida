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
        Schema::create('hemocentro', function (Blueprint $table): void {
            $table->char('cnes_hem', 7)->primary();
            $table->char('cnpj_hem', 14)->unique();
            $table->string('endereco_hem', 255);
            $table->string('contato_hem', 20);
            $table->string('senha_hem', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hemocentro');
    }
};
