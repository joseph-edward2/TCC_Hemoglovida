<?php

namespace App\Console\Commands;

use App\Enums\StatusBolsa;
use App\Enums\StatusPedido;
use App\Models\BolsaSangue;
use App\Models\Pedido;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Simula, apenas fora de produção, o envio feito pelo hemocentro. Em produção
 * esse evento deve ser registrado pela tela do hemocentro; aqui ele serve para
 * demonstrar a chegada e a confirmação na tela do hospital.
 */
#[Signature('hospital:simular-envio {id_ped} {--bolsas=}')]
#[Description('Simula o envio das bolsas de um pedido (uso restrito fora de produção).')]
class SimularEnvioPedido extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Este comando só pode ser executado fora de produção.');

            return self::FAILURE;
        }

        $pedido = Pedido::find($this->argument('id_ped'));

        if (! $pedido instanceof Pedido) {
            $this->error('Pedido não encontrado.');

            return self::FAILURE;
        }

        if ($pedido->status_ped !== StatusPedido::Pendente
            && $pedido->status_ped !== StatusPedido::EmAnalise
            && $pedido->status_ped !== StatusPedido::EmSeparacao) {
            $this->error('Só é possível simular o envio de um pedido ainda não enviado.');

            return self::FAILURE;
        }

        $quantidade = (int) ($this->option('bolsas') ?? $pedido->quant_ped);

        if ($quantidade < 1 || $quantidade > 50) {
            $this->error('A quantidade de bolsas deve estar entre 1 e 50.');

            return self::FAILURE;
        }

        $bolsas = BolsaSangue::query()
            ->where('cnes_hem', $pedido->cnes_hem)
            ->whereNull('cnpj_hosp')
            ->where('tipo_bolsa', $pedido->tipo_sanguineo_ped?->value)
            ->disponiveis()
            ->where('data_venc_bolsa', '>=', today()->toDateString())
            ->orderBy('data_venc_bolsa')
            ->orderBy('isbt')
            ->limit($quantidade)
            ->get();

        if ($bolsas->count() < $quantidade) {
            $this->error('Não há bolsas suficientes no hemocentro para simular o envio.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($pedido, $bolsas): void {
            $pedido->status_ped = StatusPedido::Enviado->value;
            $pedido->data_hora_envio_ped = now();
            $pedido->save();

            foreach ($bolsas as $bolsa) {
                $bolsa->cnpj_hosp = $pedido->cnpj_hosp;
                $bolsa->id_ped = $pedido->id_ped;
                $bolsa->data_hora_envio_bolsa = now();
                $bolsa->disponibilidade_bolsa = StatusBolsa::EmTransito->value;
                $bolsa->save();
            }
        });

        $this->info('Envio simulado: '.$bolsas->count().' bolsa(s) a caminho do hospital.');

        return self::SUCCESS;
    }
}
