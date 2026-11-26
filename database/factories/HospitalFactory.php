<?php

namespace Database\Factories;

use App\Models\Hospital;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hospital>
 */
class HospitalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cnes_hosp' => sprintf('%07d', fake()->unique()->numberBetween(1, 9999999)),
            'cnpj_hosp' => fake()->unique()->numerify('##############'),
            'endereco_hosp' => fake()->streetAddress(),
            'contato_hosp' => fake()->numerify('###########'),
            'nome_hosp' => fake()->company(),
            'senha_hosp' => 'test-password',
        ];
    }
}
