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
        Schema::create('notificacao', function (Blueprint $table): void {
            $table->id('id_notificacao');
            $table->char('cpf_doador', 11);
            $table->unsignedBigInteger('id_evento')->nullable();
            $table->unsignedBigInteger('protocolo_agendamento')->nullable();
            $table->string('tipo_notificacao', 100);
            $table->string('dist_notificacao', 255)->nullable();
            $table->dateTime('criada_em')->useCurrent();

            $table->foreign('cpf_doador', 'fk_notificacao_doador')
                ->references('cpf_doador')->on('doador');
            $table->foreign('id_evento', 'fk_notificacao_evento')
                ->references('id_evento')->on('evento');
            $table->foreign('protocolo_agendamento', 'fk_notificacao_agendamento')
                ->references('protocolo_agendamento')->on('agendamento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificacao');
    }
};
