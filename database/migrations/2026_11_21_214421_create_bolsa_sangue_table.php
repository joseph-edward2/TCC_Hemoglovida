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
        Schema::create('bolsa_sangue', function (Blueprint $table): void {
            $table->char('isbt', 20)->primary();
            $table->char('cnes_hem', 7);
            $table->integer('quant_bolsa')->default(0);
            $table->string('tipo_bolsa', 50);
            $table->string('disponibilidade_bolsa', 30);
            $table->date('data_venc_bolsa');

            $table->foreign('cnes_hem', 'fk_bolsa_hemocentro')
                ->references('cnes_hem')->on('hemocentro');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bolsa_sangue');
    }
};
