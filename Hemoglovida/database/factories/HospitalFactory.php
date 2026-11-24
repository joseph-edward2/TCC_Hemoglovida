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
            'cnpj_hosp' => self::validCnpj(),
            'cnes_hosp' => sprintf('%07d', fake()->unique()->numberBetween(1, 9999999)),
            'nome_hosp' => fake()->company(),
            'contato_hosp' => fake()->phoneNumber(),
            'endereco_hosp' => fake()->address(),
            'senha_hosp' => 'password',
        ];
    }

    private static function validCnpj(): string
    {
        $cnpj = sprintf('%08d0001', fake()->unique()->numberBetween(1, 99999999));

        $weightsOne = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $weightsTwo = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        foreach ([$weightsOne, $weightsTwo] as $weights) {
            $sum = 0;

            foreach ($weights as $index => $weight) {
                $sum += ((int) $cnpj[$index]) * $weight;
            }

            $remainder = $sum % 11;
            $cnpj .= (string) ($remainder < 2 ? 0 : 11 - $remainder);
        }

        return $cnpj;
    }
}
