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
            $table->string('nome_hem')->nullable()->after('cnes_hem');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hemocentro', function (Blueprint $table): void {
            $table->dropColumn('nome_hem');
        });
    }
};
