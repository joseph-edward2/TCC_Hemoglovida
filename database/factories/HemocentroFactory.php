<?php

namespace Database\Factories;

use App\Models\Hemocentro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hemocentro>
 */
class HemocentroFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cnes_hem' => sprintf('%07d', fake()->unique()->numberBetween(1, 9999999)),
            'cnpj_hem' => fake()->unique()->numerify('##############'),
            'endereco_hem' => fake()->streetAddress(),
            'contato_hem' => fake()->numerify('###########'),
            'senha_hem' => 'test-password',
        ];
    }
}
