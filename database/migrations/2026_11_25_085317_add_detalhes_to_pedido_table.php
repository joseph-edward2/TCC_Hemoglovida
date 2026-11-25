<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pedido', function (Blueprint $table): void {
            $table->string('tipo_sanguineo_ped', 3)->nullable();
            $table->string('urgencia_ped', 20)->nullable();
            $table->string('paciente_setor_ped')->nullable();
            $table->dateTime('data_hora_envio_ped')->nullable();
        });

        foreach (['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'] as $tipo) {
            DB::table('pedido')->whereNull('tipo_sanguineo_ped')
                ->whereIn('desc_ped', [$tipo, "Bolsas {$tipo}", "Pedido de bolsas {$tipo}", "Pedido de demonstração de bolsas {$tipo}"])
                ->update(['tipo_sanguineo_ped' => $tipo]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pedido', function (Blueprint $table): void {
            $table->dropColumn(['tipo_sanguineo_ped', 'urgencia_ped', 'paciente_setor_ped', 'data_hora_envio_ped']);
        });
    }
};
