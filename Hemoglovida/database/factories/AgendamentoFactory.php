<?php

namespace Database\Factories;

use App\Models\Agendamento;
use App\Models\Doador;
use App\Models\Evento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agendamento>
 */
class AgendamentoFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function (Agendamento $agendamento): void {
            if ($agendamento->cnes_hem === null) {
                $agendamento->cnes_hem = $agendamento->evento->cnes_hem;
            }
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cpf_doador' => Doador::factory(),
            'id_evento' => Evento::factory(),
            'cnes_hem' => null,
            'data_hora_agendamento' => fake()->dateTimeBetween('+1 day', '+1 month'),
            'status_agendamento' => 'agendado',
        ];
    }
}
