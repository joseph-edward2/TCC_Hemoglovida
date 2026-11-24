<?php

namespace Database\Factories;

use App\Models\Evento;
use App\Models\Hemocentro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evento>
 */
class EventoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cnes_hem' => Hemocentro::factory(),
            'tipo_evento' => 'Coleta de sangue',
            'data_hora_evento' => fake()->dateTimeBetween('+1 day', '+1 month'),
            'local_evento' => fake()->address(),
            'desc_evento' => fake()->sentence(),
        ];
    }
}
