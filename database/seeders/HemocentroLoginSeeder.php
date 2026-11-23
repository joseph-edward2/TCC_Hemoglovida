<?php

namespace Database\Seeders;

use App\Models\Funcionario;
use App\Models\Hemocentro;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class HemocentroLoginSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->isLocal() || Hemocentro::query()->exists() || Funcionario::query()->exists()) {
            throw new RuntimeException('O cadastro de demonstração exige um banco local sem hemocentros e funcionários.');
        }

        $password = $this->command?->secret('Escolha a senha dos dois acessos de demonstração');

        if (! is_string($password) || mb_strlen($password) < 8 || strlen($password) > 72) {
            throw new RuntimeException('A senha precisa ter pelo menos 8 caracteres e no máximo 72 bytes.');
        }

        $hemocentro = DB::transaction(function () use ($password): Hemocentro {
            $hemocentro = Hemocentro::factory()->create([
                'cnes_hem' => '0000001',
                'senha_hem' => $password,
            ]);

            Funcionario::factory()->create([
                'cnes_hem' => $hemocentro->cnes_hem,
                'cpf_fun' => '28001238938',
                'nome_fun' => 'Funcionário de demonstração',
                'senha_fun' => $password,
            ]);

            return $hemocentro;
        });

        $this->command?->info("CNES: {$hemocentro->cnes_hem} | CPF: 280.012.389-38");
    }
}
