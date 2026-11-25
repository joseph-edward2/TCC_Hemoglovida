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
        Schema::table('evento', function (Blueprint $table): void {
            $table->string('titulo_evento')->nullable();
            $table->string('status_evento', 20)->default('publicado');
            $table->json('tipos_sanguineos')->nullable();
            $table->string('urgencia', 20)->nullable();
            $table->string('publico_alvo')->nullable();
            $table->date('expiracao_evento')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evento', function (Blueprint $table): void {
            $table->dropColumn(['titulo_evento', 'status_evento', 'tipos_sanguineos', 'urgencia', 'publico_alvo', 'expiracao_evento']);
        });
    }
};
