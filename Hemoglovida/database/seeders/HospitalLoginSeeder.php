<?php

namespace Database\Seeders;

use App\Enums\CargoFuncionario;
use App\Models\Funcionario;
use App\Models\Hospital;
use Illuminate\Database\Seeder;

class HospitalLoginSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Hospital::firstOrCreate(
            ['cnpj_hosp' => '11222333000181'],
            [
                'cnes_hosp' => '2222222',
                'nome_hosp' => 'Hospital Santa Casa',
                'contato_hosp' => '(11) 3000-0000',
                'endereco_hosp' => 'Rua do Hospital, 500',
                'senha_hosp' => 'hospital123',
            ],
        );

        Hospital::firstOrCreate(
            ['cnpj_hosp' => '98765432000198'],
            [
                'cnes_hosp' => '3333333',
                'nome_hosp' => 'Hospital Municipal',
                'contato_hosp' => '(11) 3000-0001',
                'endereco_hosp' => 'Avenida Municipal, 700',
                'senha_hosp' => 'hospital123',
            ],
        );

        Funcionario::updateOrCreate(
            ['cpf_fun' => '52998224725'],
            [
                'cnpj_hosp' => '11222333000181',
                'nome_fun' => 'Renato Gonçalves',
                'senha_fun' => 'funcionario123',
                'tipo_fun' => CargoFuncionario::Responsavel->value,
            ],
        );

        $this->command?->info('Hospital 1: CNPJ 11.222.333/0001-81 (CNES 2222222) | senha: hospital123');
        $this->command?->info('Hospital 2: CNPJ 98.765.432/0001-98 (CNES 3333333) | senha: hospital123');
        $this->command?->info('Funcionário responsável: CPF 529.982.247-25 (Renato Gonçalves) | senha: funcionario123');
    }
}
