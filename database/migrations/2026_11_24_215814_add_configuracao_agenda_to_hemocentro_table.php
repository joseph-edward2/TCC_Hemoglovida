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
        Schema::table('hemocentro', function (Blueprint $table): void {
            $table->json('configuracao_agenda')->nullable();
            $table->unsignedInteger('versao_agenda')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hemocentro', function (Blueprint $table): void {
            $table->dropColumn(['configuracao_agenda', 'versao_agenda']);
        });
    }
};
