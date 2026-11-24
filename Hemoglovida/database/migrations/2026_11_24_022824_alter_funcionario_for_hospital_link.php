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
        Schema::table('funcionario', function (Blueprint $table): void {
            $table->char('cnes_hem', 7)->nullable()->change();
            $table->char('cnpj_hosp', 14)->nullable();

            $table->foreign('cnpj_hosp', 'fk_funcionario_hospital')
                ->references('cnpj_hosp')->on('hospital');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funcionario', function (Blueprint $table): void {
            $table->dropForeign('fk_funcionario_hospital');
        });

        Schema::table('funcionario', function (Blueprint $table): void {
            $table->dropColumn('cnpj_hosp');
            $table->char('cnes_hem', 7)->nullable(false)->change();
        });
    }
};
