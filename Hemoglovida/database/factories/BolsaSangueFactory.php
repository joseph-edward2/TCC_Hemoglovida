<?php

namespace Database\Factories;

use App\Enums\StatusBolsa;
use App\Enums\TipoSanguineo;
use App\Models\BolsaSangue;
use App\Models\Hemocentro;
use App\Models\Hospital;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BolsaSangue>
 */
class BolsaSangueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'isbt' => fake()->unique()->numerify('####################'),
            'cnes_hem' => Hemocentro::factory(),
            'quant_bolsa' => 1,
            'tipo_bolsa' => fake()->randomElement(TipoSanguineo::cases())->value,
            'disponibilidade_bolsa' => StatusBolsa::Disponivel->value,
            'data_venc_bolsa' => today()->addDays(30),
            'cnpj_hosp' => null,
            'id_ped' => null,
            'data_hora_envio_bolsa' => null,
            'data_hora_recebimento_bolsa' => null,
            'paciente_destino_bolsa' => null,
        ];
    }

    public function vencida(): static
    {
        return $this->state(fn (array $attributes): array => [
            'data_venc_bolsa' => today()->subDay(),
        ]);
    }

    public function vencendoEm(int $dias): static
    {
        return $this->state(fn (array $attributes): array => [
            'data_venc_bolsa' => today()->addDays($dias),
        ]);
    }

    public function noHemocentro(Hemocentro $hemocentro): static
    {
        return $this->state(fn (array $attributes): array => [
            'cnes_hem' => $hemocentro->cnes_hem,
        ]);
    }

    public function comDisponibilidade(string $disponibilidade): static
    {
        return $this->state(fn (array $attributes): array => [
            'disponibilidade_bolsa' => $disponibilidade,
        ]);
    }

    public function emTransito(): static
    {
        return $this->state(fn (array $attributes): array => [
            'disponibilidade_bolsa' => StatusBolsa::EmTransito->value,
        ]);
    }

    public function noHospital(Hospital $hospital): static
    {
        return $this->state(fn (array $attributes): array => [
            'cnpj_hosp' => $hospital->cnpj_hosp,
        ]);
    }
}
