<?php

namespace Database\Seeders;

use App\Models\Hemocentro;
use Illuminate\Database\Seeder;

class HemocentroGuarulhosSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $hemocentro = Hemocentro::firstOrCreate(
            ['cnes_hem' => '2080338'],
            [
                'cnpj_hem' => '46374500008926',
                'endereco_hem' => 'Alameda dos Lírios, 300 - Parque Cecap, Guarulhos',
                'contato_hem' => '(11) 3466-1350',
                'senha_hem' => 'Guaru1234',
            ],
        );

        $this->command?->info("Hemocentro: CNES {$hemocentro->cnes_hem} | CNPJ {$hemocentro->cnpj_hem}");
    }
}
