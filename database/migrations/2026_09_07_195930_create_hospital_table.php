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
        Schema::create('hospital', function (Blueprint $table): void {
            $table->char('cnpj_hosp', 14)->primary();
            $table->char('cnes_hosp', 7)->unique();
            $table->string('nome_hosp', 150);
            $table->string('contato_hosp', 20);
            $table->string('endereco_hosp', 255);
            $table->string('senha_hosp', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hospital');
    }
};
