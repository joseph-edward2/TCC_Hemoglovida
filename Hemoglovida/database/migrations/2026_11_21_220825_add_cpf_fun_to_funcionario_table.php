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
            $table->char('cpf_fun', 11)->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funcionario', function (Blueprint $table): void {
            $table->dropUnique(['cpf_fun']);
            $table->dropColumn('cpf_fun');
        });
    }
};
