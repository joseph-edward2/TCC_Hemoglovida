<?php

namespace Database\Factories;

use App\Models\Funcionario;
use App\Models\Hemocentro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Funcionario>
 */
class FuncionarioFactory extends Factory
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
            'nome_fun' => fake()->name(),
            'senha_fun' => 'test-password',
            'tipo_fun' => 'administrativo_hemocentro',
        ];
    }
}
