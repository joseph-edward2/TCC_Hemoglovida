<?php

namespace Database\Factories;

use App\Enums\TipoSanguineo;
use App\Enums\UrgenciaPedido;
use App\Models\Funcionario;
use App\Models\Hemocentro;
use App\Models\Hospital;
use App\Models\Pedido;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pedido>
 */
class PedidoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cnpj_hosp' => Hospital::factory(),
            'cnes_hem' => Hemocentro::factory(),
            'cod_reg_fun' => Funcionario::factory(),
            'desc_ped' => fake()->sentence(),
            'data_hora_ped' => fake()->dateTimeBetween('-1 month', 'now'),
            'status_ped' => 'pendente',
            'quant_ped' => fake()->numberBetween(1, 10),
            'url_requisicao_transfusao_ped' => 'requisicoes/'.fake()->uuid().'.pdf',
            'tipo_sanguineo_ped' => fake()->randomElement(TipoSanguineo::cases())->value,
            'urgencia_ped' => UrgenciaPedido::Rotina->value,
            'data_hora_envio_ped' => null,
            'data_hora_entrega_ped' => null,
        ];
    }

    public function comStatusPed(string $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status_ped' => $status,
        ]);
    }

    public function noHospital(Hospital $hospital): static
    {
        return $this->state(fn (array $attributes): array => [
            'cnpj_hosp' => $hospital->cnpj_hosp,
        ]);
    }

    public function pedidoCritico(): static
    {
        return $this->state(fn (array $attributes): array => [
            'urgencia_ped' => UrgenciaPedido::Critica->value,
        ]);
    }
}
