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
            $table->dateTime('data_hora_saida_bolsa')->nullable()->after('data_hora_recebimento_bolsa');
            $table->index('data_hora_saida_bolsa', 'idx_bolsa_saida');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bolsa_sangue', function (Blueprint $table): void {
            $table->dropIndex('idx_bolsa_saida');
            $table->dropColumn('data_hora_saida_bolsa');
        });
    }
};
