<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('funcionario')
            ->where('tipo_fun', 'administrativo_hemocentro')
            ->update(['tipo_fun' => 'Administrativo']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('funcionario')
            ->where('tipo_fun', 'Administrativo')
            ->update(['tipo_fun' => 'administrativo_hemocentro']);
    }
};
