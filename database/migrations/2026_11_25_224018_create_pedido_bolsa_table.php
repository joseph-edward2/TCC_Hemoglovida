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
        Schema::create('pedido_bolsa', function (Blueprint $table): void {
            $table->foreignId('id_ped')->constrained('pedido', 'id_ped');
            $table->char('isbt', 20);
            $table->foreign('isbt')->references('isbt')->on('bolsa_sangue');
            $table->unsignedInteger('quantidade');
            $table->primary(['id_ped', 'isbt']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedido_bolsa');
    }
};
