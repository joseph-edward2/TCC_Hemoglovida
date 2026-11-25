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
        Schema::create('mensagem_recusa_pedido', function (Blueprint $table): void {
            $table->foreignId('id_ped')->primary()->constrained('pedido', 'id_ped');
            $table->char('cnpj_hosp', 14);
            $table->foreign('cnpj_hosp')->references('cnpj_hosp')->on('hospital');
            $table->text('motivo');
            $table->dateTime('data_hora_mensagem')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mensagem_recusa_pedido');
    }
};
