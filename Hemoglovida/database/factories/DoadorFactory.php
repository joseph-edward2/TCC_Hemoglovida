<?php

namespace Database\Factories;

use App\Models\Doador;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doador>
 */
class DoadorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cpf_doador' => fake()->unique()->numerify('###########'),
            'nome_doador' => fake()->name(),
            'email_doador' => fake()->unique()->safeEmail(),
            'senha_doador' => 'password',
            'fone_doador' => fake()->phoneNumber(),
            'tipo_sanguineo' => fake()->randomElement(['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+']),
            'endereco_doador' => fake()->address(),
            'sexo_doador' => fake()->randomElement(['M', 'F']),
            'data_nasc_doador' => fake()->date(),
        ];
    }
}
