<?php

namespace Database\Seeders;

use App\Enums\CargoFuncionario;
use App\Models\Funcionario;
use App\Models\Hemocentro;
use Illuminate\Database\Seeder;

class HemocentroLoginSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $hemocentro = Hemocentro::firstOrCreate(
            ['cnes_hem' => '0000001'],
            [
                'cnpj_hem' => '22333444000181',
                'endereco_hem' => 'Rua Central, 100',
                'contato_hem' => '(11) 4000-0001',
                'senha_hem' => 'hemocentro123',
            ],
        );

        Funcionario::firstOrCreate(
            ['cpf_fun' => '28001238938'],
            [
                'cnes_hem' => $hemocentro->cnes_hem,
                'nome_fun' => 'Funcionário de demonstração',
                'senha_fun' => 'funcionario123',
                'tipo_fun' => CargoFuncionario::Administrativo->value,
            ],
        );

        $this->command?->info('Hemocentro: CNES 0000001 | senha: hemocentro123');
        $this->command?->info('Funcionário: CPF 280.012.389-38 | senha: funcionario123');
    }
}
