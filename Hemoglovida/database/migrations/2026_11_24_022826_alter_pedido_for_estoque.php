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
        Schema::table('pedido', function (Blueprint $table): void {
            $table->unsignedBigInteger('cod_reg_fun')->nullable()->change();
            $table->string('tipo_sanguineo_ped', 3)->nullable();
            $table->string('urgencia_ped', 20)->default('rotina');
            $table->dateTime('data_hora_envio_ped')->nullable();
            $table->dateTime('data_hora_entrega_ped')->nullable();

            $table->index(['cnpj_hosp', 'status_ped'], 'idx_pedido_hosp_status');
            $table->index(['cnpj_hosp', 'data_hora_ped'], 'idx_pedido_hosp_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pedido', function (Blueprint $table): void {
            $table->dropIndex('idx_pedido_hosp_status');
            $table->dropIndex('idx_pedido_hosp_data');
            $table->dropColumn([
                'tipo_sanguineo_ped',
                'urgencia_ped',
                'data_hora_envio_ped',
                'data_hora_entrega_ped',
            ]);
            $table->unsignedBigInteger('cod_reg_fun')->nullable(false)->change();
        });
    }
};
