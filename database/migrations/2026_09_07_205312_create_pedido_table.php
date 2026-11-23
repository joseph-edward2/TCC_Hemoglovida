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
        Schema::create('pedido', function (Blueprint $table): void {
            $table->id('id_ped');
            $table->char('cnpj_hosp', 14);
            $table->char('cnes_hem', 7);
            $table->unsignedBigInteger('cod_reg_fun');
            $table->text('desc_ped');
            $table->dateTime('data_hora_ped')->useCurrent();
            $table->string('status_ped', 30)->default('pendente');
            $table->integer('quant_ped');
            $table->string('url_requisicao_transfusao_ped', 255)->nullable();

            $table->foreign('cnpj_hosp', 'fk_pedido_hospital')
                ->references('cnpj_hosp')->on('hospital');
            $table->foreign('cnes_hem', 'fk_pedido_hemocentro')
                ->references('cnes_hem')->on('hemocentro');
            $table->foreign('cod_reg_fun', 'fk_pedido_funcionario')
                ->references('cod_reg_fun')->on('funcionario');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedido');
    }
};
