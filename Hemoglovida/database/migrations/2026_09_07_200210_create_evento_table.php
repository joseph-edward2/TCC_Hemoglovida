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
        Schema::create('evento', function (Blueprint $table): void {
            $table->id('id_evento');
            $table->char('cnes_hem', 7);
            $table->string('tipo_evento', 100);
            $table->dateTime('data_hora_evento');
            $table->string('local_evento', 255);
            $table->text('desc_evento')->nullable();

            $table->foreign('cnes_hem', 'fk_evento_hemocentro')
                ->references('cnes_hem')->on('hemocentro');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evento');
    }
};
