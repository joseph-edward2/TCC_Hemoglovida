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
        Schema::create('agendamento', function (Blueprint $table): void {
            $table->id('protocolo_agendamento');
            $table->char('cpf_doador', 11);
            $table->char('cnes_hem', 7);
            $table->unsignedBigInteger('id_evento');
            $table->dateTime('data_hora_agendamento');
            $table->dateTime('data_hora_coleta')->nullable();
            $table->string('status_agendamento', 30)->default('agendado');
            $table->string('status_bolsa_doacao', 30)->nullable();
            $table->decimal('volume_ml_doacao', 6, 2)->nullable();

            $table->foreign('cpf_doador', 'fk_agendamento_doador')
                ->references('cpf_doador')->on('doador');
            $table->foreign('cnes_hem', 'fk_agendamento_hemocentro')
                ->references('cnes_hem')->on('hemocentro');
            $table->foreign('id_evento', 'fk_agendamento_evento')
                ->references('id_evento')->on('evento');
            $table->unique(['cpf_doador', 'data_hora_agendamento'], 'uq_doador_horario');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agendamento');
    }
};
