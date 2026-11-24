<?php

namespace Database\Factories;

use App\Enums\CargoFuncionario;
use App\Models\Funcionario;
use App\Models\Hemocentro;
use App\Models\Hospital;
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
            'tipo_fun' => CargoFuncionario::Administrativo->value,
        ];
    }

    public function paraHospital(Hospital $hospital): static
    {
        return $this->state(fn (array $attributes): array => [
            'cnes_hem' => null,
            'cnpj_hosp' => $hospital->cnpj_hosp,
        ]);
    }

    public function paraHemocentro(Hemocentro $hemocentro): static
    {
        return $this->state(fn (array $attributes): array => [
            'cnes_hem' => $hemocentro->cnes_hem,
            'cnpj_hosp' => null,
        ]);
    }
}
