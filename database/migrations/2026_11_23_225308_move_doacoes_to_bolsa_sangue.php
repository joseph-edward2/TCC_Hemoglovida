<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bolsa_sangue', function (Blueprint $table): void {
            $table->foreignId('protocolo_agendamento')->nullable()->unique()
                ->constrained('agendamento', 'protocolo_agendamento');
            $table->dateTime('data_hora_coleta')->nullable();
            $table->string('status_bolsa_doacao', 30)->nullable();
            $table->decimal('volume_ml_doacao', 6, 2)->nullable();
            $table->date('data_venc_bolsa')->nullable()->change();
        });

        if (! Schema::hasTable('doacao')) {
            return;
        }

        DB::transaction(function (): void {
            DB::table('doacao')
                ->leftJoin('agendamento', 'agendamento.protocolo_agendamento', '=', 'doacao.protocolo_agendamento')
                ->leftJoin('doador', 'doador.cpf_doador', '=', 'agendamento.cpf_doador')
                ->select('doacao.*', 'agendamento.cnes_hem', 'doador.tipo_sanguineo')
                ->chunkById(100, function (Collection $doacoes): void {
                    DB::table('bolsa_sangue')->insert($doacoes->map(fn (stdClass $doacao): array => [
                        'isbt' => 'TMP-'.Str::upper(Str::random(16)),
                        'cnes_hem' => $doacao->cnes_hem,
                        'quant_bolsa' => 1,
                        'tipo_bolsa' => $doacao->tipo_sanguineo,
                        'disponibilidade_bolsa' => 'em_analise',
                        'protocolo_agendamento' => $doacao->protocolo_agendamento,
                        'data_hora_coleta' => $doacao->data_hora_coleta,
                        'status_bolsa_doacao' => $doacao->status_bolsa_doacao,
                        'volume_ml_doacao' => $doacao->volume_ml_doacao,
                    ])->all());
                }, 'doacao.protocolo_agendamento', 'protocolo_agendamento');
        });
    }

    /**
     * Restore the collection data to doacao. The old schema has no fields for
     * stock metadata assigned to bags linked to these collections.
     */
    public function down(): void
    {
        Schema::create('doacao', function (Blueprint $table): void {
            $table->unsignedBigInteger('protocolo_agendamento')->primary();
            $table->dateTime('data_hora_coleta');
            $table->string('status_bolsa_doacao', 30);
            $table->decimal('volume_ml_doacao', 6, 2);

            $table->foreign('protocolo_agendamento', 'fk_doacao_agendamento')
                ->references('protocolo_agendamento')->on('agendamento');
        });

        DB::transaction(function (): void {
            $columns = ['protocolo_agendamento', 'data_hora_coleta', 'status_bolsa_doacao', 'volume_ml_doacao'];
            $linkedBags = DB::table('bolsa_sangue')->whereNotNull('protocolo_agendamento');

            DB::table('doacao')->insertUsing($columns, $linkedBags->select($columns));
            $linkedBags->delete();
        });

        Schema::table('bolsa_sangue', function (Blueprint $table): void {
            $table->dropForeign(['protocolo_agendamento']);
            $table->dropUnique(['protocolo_agendamento']);
            $table->dropColumn(['protocolo_agendamento', 'data_hora_coleta', 'status_bolsa_doacao', 'volume_ml_doacao']);
            $table->date('data_venc_bolsa')->nullable(false)->change();
        });
    }
};
