<?php

namespace Database\Seeders;

use App\Models\Agendamento;
use App\Models\BolsaSangue;
use App\Models\Doador;
use App\Models\Evento;
use App\Models\Funcionario;
use App\Models\Hemocentro;
use App\Models\Hospital;
use App\Models\Notificacao;
use App\Models\Pedido;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $hemocentros = Hemocentro::factory()->count(2)->create();
        $hospitais = Hospital::factory()->count(3)->create();

        $funcionarios = $hemocentros->flatMap(
            fn (Hemocentro $hemocentro) => Funcionario::factory()->count(2)->for($hemocentro)->create(),
        );

        $doadores = Doador::factory()->count(10)->create();

        $eventos = $hemocentros->flatMap(
            fn (Hemocentro $hemocentro) => Evento::factory()->count(2)->for($hemocentro)->create(),
        );

        foreach ($eventos->values() as $indice => $evento) {
            Agendamento::factory()->create([
                'cpf_doador' => $doadores->random()->cpf_doador,
                'cnes_hem' => $evento->cnes_hem,
                'id_evento' => $evento->id_evento,
                'data_hora_agendamento' => now()->addDays($indice + 1)->setTime(9 + ($indice % 8), 0),
            ]);
        }

        foreach ($hospitais as $hospital) {
            foreach ($hemocentros as $hemocentro) {
                Pedido::factory()->create([
                    'cnpj_hosp' => $hospital->cnpj_hosp,
                    'cnes_hem' => $hemocentro->cnes_hem,
                    'cod_reg_fun' => $funcionarios->random()->cod_reg_fun,
                ]);
            }
        }

        foreach ($hemocentros as $hemocentro) {
            BolsaSangue::factory()->count(10)->for($hemocentro)->create();
        }

        foreach ($doadores->take(5) as $doador) {
            Notificacao::factory()->create(['cpf_doador' => $doador->cpf_doador]);
        }
    }
}
