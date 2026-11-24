<?php

namespace Database\Seeders;

use App\Enums\CargoFuncionario;
use App\Enums\StatusBolsa;
use App\Enums\StatusPedido;
use App\Enums\TipoSanguineo;
use App\Enums\UrgenciaPedido;
use App\Models\BolsaSangue;
use App\Models\Funcionario;
use App\Models\Hemocentro;
use App\Models\Hospital;
use App\Models\Pedido;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HospitalDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the hospital inventory and orders for the demo credentials.
     */
    public function run(): void
    {
        $hospital = Hospital::find('11222333000181');

        if (! $hospital) {
            $this->call(HospitalLoginSeeder::class);
            $hospital = Hospital::findOrFail('11222333000181');
        }

        $hemocentros = Hemocentro::all();

        if ($hemocentros->isEmpty()) {
            $this->call(HemocentroLoginSeeder::class);
            $hemocentros = Hemocentro::all();
        }

        $responsavel = Funcionario::where('cnpj_hosp', $hospital->cnpj_hosp)
            ->where('tipo_fun', CargoFuncionario::Responsavel->value)
            ->first();

        if (! $responsavel) {
            $this->call(HospitalLoginSeeder::class);
            $responsavel = Funcionario::where('cnpj_hosp', $hospital->cnpj_hosp)
                ->where('tipo_fun', CargoFuncionario::Responsavel->value)
                ->firstOrFail();
        }

        if (BolsaSangue::where('cnpj_hosp', $hospital->cnpj_hosp)->exists()) {
            return;
        }

        $tipos = TipoSanguineo::cases();
        $statuses = StatusBolsa::cases();

        $bolsas = collect();

        foreach (range(1, 20) as $indice) {
            $vencimento = match ($indice % 4) {
                0 => today()->subDays(2),
                1 => today()->addDays(3),
                default => today()->addDays(30),
            };

            $bolsas->push(BolsaSangue::factory()->create([
                'cnes_hem' => $hemocentros->random()->cnes_hem,
                'cnpj_hosp' => $hospital->cnpj_hosp,
                'tipo_bolsa' => $tipos[($indice - 1) % count($tipos)]->value,
                'disponibilidade_bolsa' => $statuses[($indice - 1) % count($statuses)]->value,
                'data_venc_bolsa' => $vencimento,
            ]));
        }

        $pedidos = collect();

        foreach (StatusPedido::cases() as $statusIndex => $status) {
            foreach (UrgenciaPedido::cases() as $urgencia) {
                $pedidos->push(Pedido::factory()->create([
                    'cnpj_hosp' => $hospital->cnpj_hosp,
                    'cnes_hem' => $hemocentros->random()->cnes_hem,
                    'cod_reg_fun' => $responsavel->cod_reg_fun,
                    'tipo_sanguineo_ped' => $tipos[($statusIndex + 1) % count($tipos)]->value,
                    'urgencia_ped' => $urgencia->value,
                    'status_ped' => $status->value,
                    'desc_ped' => 'Paciente / Setor — '.$urgencia->label().' ('.$status->label().')',
                    'data_hora_envio_ped' => $status === StatusPedido::Enviado
                        ? now()->subHours(2)
                        : null,
                    'data_hora_entrega_ped' => $status === StatusPedido::Entregue
                        ? now()->subHour()
                        : null,
                ]));
            }
        }

        foreach ($pedidos->take(6) as $indice => $pedido) {
            $bolsas[$indice]->update([
                'id_ped' => $pedido->id_ped,
                'disponibilidade_bolsa' => StatusBolsa::EmTransito->value,
                'data_hora_envio_bolsa' => now()->subHours(2),
                'paciente_destino_bolsa' => 'Paciente / Setor '.$pedido->id_ped,
            ]);
        }
    }
}
