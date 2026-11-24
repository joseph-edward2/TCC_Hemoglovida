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
        Schema::table('bolsa_sangue', function (Blueprint $table): void {
            $table->char('cnpj_hosp', 14)->nullable();
            $table->unsignedBigInteger('id_ped')->nullable();
            $table->dateTime('data_hora_envio_bolsa')->nullable();
            $table->dateTime('data_hora_recebimento_bolsa')->nullable();
            $table->string('paciente_destino_bolsa', 150)->nullable();

            $table->foreign('cnpj_hosp', 'fk_bolsa_hospital')
                ->references('cnpj_hosp')->on('hospital');
            $table->foreign('id_ped', 'fk_bolsa_pedido')
                ->references('id_ped')->on('pedido');

            $table->index(
                ['cnpj_hosp', 'tipo_bolsa', 'disponibilidade_bolsa'],
                'idx_bolsa_hosp_tipo_status'
            );
            $table->index('data_venc_bolsa', 'idx_bolsa_vencimento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bolsa_sangue', function (Blueprint $table): void {
            $table->dropForeign('fk_bolsa_hospital');
            $table->dropForeign('fk_bolsa_pedido');
            $table->dropIndex('idx_bolsa_hosp_tipo_status');
            $table->dropIndex('idx_bolsa_vencimento');
            $table->dropColumn([
                'cnpj_hosp',
                'id_ped',
                'data_hora_envio_bolsa',
                'data_hora_recebimento_bolsa',
                'paciente_destino_bolsa',
            ]);
        });
    }
};
