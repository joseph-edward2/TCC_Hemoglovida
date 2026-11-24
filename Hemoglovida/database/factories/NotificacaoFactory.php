<?php

namespace Database\Factories;

use App\Models\Doador;
use App\Models\Evento;
use App\Models\Notificacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notificacao>
 */
class NotificacaoFactory extends Factory
{
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
            'protocolo_agendamento' => null,
            'tipo_notificacao' => 'agendamento_confirmado',
            'dist_notificacao' => 'email',
            'criada_em' => now(),
        ];
    }
}
