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
        Schema::create('funcionario', function (Blueprint $table): void {
            $table->id('cod_reg_fun');
            $table->char('cnes_hem', 7);
            $table->string('nome_fun', 150);
            $table->string('senha_fun', 255);
            $table->string('tipo_fun', 80);

            $table->foreign('cnes_hem', 'fk_funcionario_hemocentro')
                ->references('cnes_hem')->on('hemocentro');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funcionario');
    }
};
