<?php

use App\Enums\CargoFuncionario;
use App\Models\BolsaSangue;
use App\Models\Funcionario;
use App\Models\Hemocentro;
use App\Models\Hospital;
use App\Models\Pedido;
use Database\Seeders\HospitalDemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

uses(RefreshDatabase::class);

function migrationDeTipoFunLegado(): string
{
    $arquivos = glob(database_path('migrations/*migrate_legacy_tipo_fun.php'));

    expect($arquivos)->not->toBeEmpty();

    return $arquivos[0];
}

it('vincula o funcionário ao hospital sem hemocentro', function () {
    $hospital = Hospital::factory()->create();

    $funcionario = Funcionario::factory()->paraHospital($hospital)->create();

    expect($funcionario->cnes_hem)->toBeNull()
        ->and($funcionario->cnpj_hosp)->toBe($hospital->cnpj_hosp)
        ->and($funcionario->hospital)->toBeInstanceOf(Hospital::class)
        ->and($funcionario->instituicao())->toBeInstanceOf(Hospital::class);
});

it('rejeita vínculo duplo e vínculo nulo no funcionário', function () {
    $hospital = Hospital::factory()->create();
    $hemocentro = Hemocentro::factory()->create();

    expect(fn () => Funcionario::factory()->create([
        'cnes_hem' => $hemocentro->cnes_hem,
        'cnpj_hosp' => $hospital->cnpj_hosp,
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn () => Funcionario::factory()->create([
        'cnes_hem' => null,
        'cnpj_hosp' => null,
    ]))->toThrow(InvalidArgumentException::class);
});

it('exige um hospital existente no vínculo do funcionário', function () {
    expect(fn () => Funcionario::factory()->create([
        'cnes_hem' => null,
        'cnpj_hosp' => '99999999999999',
    ]))->toThrow(QueryException::class);
});

it('converte o tipo_fun legado e reverte no down da migration', function () {
    $hemocentro = Hemocentro::factory()->create();

    $codigo = DB::table('funcionario')->insertGetId([
        'cnes_hem' => $hemocentro->cnes_hem,
        'nome_fun' => 'Funcionário legado',
        'senha_fun' => 'senha123',
        'tipo_fun' => 'administrativo_hemocentro',
    ]);

    $migration = require migrationDeTipoFunLegado();

    $migration->up();

    expect(DB::table('funcionario')->where('cod_reg_fun', $codigo)->value('tipo_fun'))
        ->toBe(CargoFuncionario::Administrativo->value);

    $migration->down();

    expect(DB::table('funcionario')->where('cod_reg_fun', $codigo)->value('tipo_fun'))
        ->toBe('administrativo_hemocentro');
});

it('preserva as foreign keys após a reconstrução das tabelas no SQLite', function () {
    $funcionario = collect(DB::select('PRAGMA foreign_key_list(funcionario)'))->pluck('table')->all();
    $pedido = collect(DB::select('PRAGMA foreign_key_list(pedido)'))->pluck('table')->all();
    $bolsa = collect(DB::select('PRAGMA foreign_key_list(bolsa_sangue)'))->pluck('table')->all();

    expect($funcionario)->toContain('hemocentro')->toContain('hospital')
        ->and($pedido)->toContain('hospital')->toContain('hemocentro')->toContain('funcionario')
        ->and($bolsa)->toContain('hemocentro')->toContain('hospital')->toContain('pedido');
});

it('adiciona colunas, nullable e índices exigidos pelas telas', function () {
    expect(Schema::hasColumns('funcionario', ['cnes_hem', 'cnpj_hosp', 'cpf_fun']))->toBeTrue()
        ->and(Schema::hasColumns('pedido', [
            'cod_reg_fun',
            'tipo_sanguineo_ped',
            'urgencia_ped',
            'data_hora_envio_ped',
            'data_hora_entrega_ped',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('bolsa_sangue', [
            'cnpj_hosp',
            'id_ped',
            'data_hora_envio_bolsa',
            'data_hora_recebimento_bolsa',
            'paciente_destino_bolsa',
        ]))->toBeTrue();

    $colunasFuncionario = collect(DB::select('PRAGMA table_info(funcionario)'))->keyBy('name');
    $colunasPedido = collect(DB::select('PRAGMA table_info(pedido)'))->keyBy('name');

    expect($colunasFuncionario->get('cnes_hem')->notnull)->toBe(0)
        ->and($colunasFuncionario->get('cnpj_hosp')->notnull)->toBe(0)
        ->and($colunasPedido->get('cod_reg_fun')->notnull)->toBe(0);

    $indicesPedido = collect(DB::select('PRAGMA index_list(pedido)'))->pluck('name')->all();
    $indicesBolsa = collect(DB::select('PRAGMA index_list(bolsa_sangue)'))->pluck('name')->all();

    expect($indicesPedido)->toContain('idx_pedido_hosp_status')->toContain('idx_pedido_hosp_data')
        ->and($indicesBolsa)->toContain('idx_bolsa_hosp_tipo_status')->toContain('idx_bolsa_vencimento');
});

it('aceita pedido sem funcionário e bolsa ligada a hospital e pedido', function () {
    $hospital = Hospital::factory()->create();
    $hemocentro = Hemocentro::factory()->create();

    $pedido = Pedido::factory()->noHospital($hospital)->create([
        'cnes_hem' => $hemocentro->cnes_hem,
        'cod_reg_fun' => null,
    ]);

    $bolsa = BolsaSangue::factory()->noHospital($hospital)->create([
        'cnes_hem' => $hemocentro->cnes_hem,
        'id_ped' => $pedido->id_ped,
    ]);

    expect($pedido->cod_reg_fun)->toBeNull()
        ->and($bolsa->pedido)->toBeInstanceOf(Pedido::class)
        ->and($bolsa->hospital)->toBeInstanceOf(Hospital::class)
        ->and($pedido->bolsas->pluck('isbt')->all())->toContain($bolsa->isbt);
});

it('semeia o inventário e os pedidos de demonstração do hospital', function () {
    $this->seed(HospitalDemoSeeder::class);

    expect(BolsaSangue::where('cnpj_hosp', '11222333000181')->count())->toBe(20)
        ->and(Pedido::where('cnpj_hosp', '11222333000181')->count())->toBe(15)
        ->and(Funcionario::where('cnpj_hosp', '11222333000181')
            ->where('tipo_fun', CargoFuncionario::Responsavel->value)
            ->exists())->toBeTrue();
});
