<?php

use App\Enums\CargoFuncionario;
use App\Enums\StatusBolsa;
use App\Enums\TipoSanguineo;
use App\Models\BolsaSangue;
use App\Models\Funcionario;
use App\Models\Hemocentro;
use App\Models\Hospital;
use App\Models\Pedido;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

uses(RefreshDatabase::class);

it('hashes the hospital password and hides it from serialization', function () {
    $hospital = Hospital::factory()->create(['senha_hosp' => 'hospital123']);

    expect(Hash::check('hospital123', $hospital->senha_hosp))->toBeTrue()
        ->and($hospital->getAuthPasswordName())->toBe('senha_hosp')
        ->and($hospital->toArray())->not->toHaveKey('senha_hosp');
});

it('hashes the employee password', function () {
    $funcionario = Funcionario::factory()->create(['senha_fun' => 'senha123']);

    expect(Hash::check('senha123', $funcionario->senha_fun))->toBeTrue()
        ->and($funcionario->tipo_fun)->toBeInstanceOf(CargoFuncionario::class);
});

it('rejects a second hospital with the same cnpj', function () {
    $cnpj = '11222333000181';
    Hospital::factory()->create(['cnpj_hosp' => $cnpj]);

    expect(fn () => Hospital::factory()->create(['cnpj_hosp' => $cnpj]))->toThrow(QueryException::class);
});

it('rejects a second hospital with the same cnes', function () {
    Hospital::factory()->create(['cnes_hosp' => '1111111']);

    expect(fn () => Hospital::factory()->create(['cnes_hosp' => '1111111']))->toThrow(QueryException::class);
});

it('rejects a second employee with the same cpf', function () {
    $cpf = '28001238938';
    Funcionario::factory()->create(['cpf_fun' => $cpf]);

    expect(fn () => Funcionario::factory()->create(['cpf_fun' => $cpf]))->toThrow(QueryException::class);
});

it('rejects a second blood bag with the same isbt', function () {
    $isbt = 'BR000000000000000001';
    BolsaSangue::factory()->create(['isbt' => $isbt]);

    expect(fn () => BolsaSangue::factory()->create(['isbt' => $isbt]))->toThrow(QueryException::class);
});

it('accepts an employee linked to a hemocenter', function () {
    $hemocentro = Hemocentro::factory()->create();

    $funcionario = Funcionario::factory()->create(['cnes_hem' => $hemocentro->cnes_hem]);

    expect($funcionario->hemocentro)->toBeInstanceOf(Hemocentro::class)
        ->and($funcionario->cnes_hem)->toBe($hemocentro->cnes_hem);
});

it('accepts an employee without a cpf', function () {
    $funcionario = Funcionario::factory()->create(['cpf_fun' => null]);

    expect($funcionario->cpf_fun)->toBeNull()
        ->and($funcionario->tipo_fun)->toBeInstanceOf(CargoFuncionario::class);
});

it('rejects an employee without an institution', function () {
    expect(fn () => Funcionario::factory()->create([
        'cnes_hem' => null,
        'cnpj_hosp' => null,
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects an employee linked to an unknown hemocenter', function () {
    expect(fn () => Funcionario::factory()->create(['cnes_hem' => '9999999']))->toThrow(QueryException::class);
});

it('resolves hospital, employee and hemocenter relationships', function () {
    $hospital = Hospital::factory()->create();
    $hemocentro = Hemocentro::factory()->create();
    $funcionario = Funcionario::factory()->create(['cnes_hem' => $hemocentro->cnes_hem]);
    $pedido = Pedido::factory()->create([
        'cnpj_hosp' => $hospital->cnpj_hosp,
        'cnes_hem' => $hemocentro->cnes_hem,
        'cod_reg_fun' => $funcionario->cod_reg_fun,
    ]);
    $bolsa = BolsaSangue::factory()->noHemocentro($hemocentro)->create();

    expect(Pedido::where('cnpj_hosp', $hospital->cnpj_hosp)->first())->toBeInstanceOf(Pedido::class)
        ->and($pedido->hospital)->toBeInstanceOf(Hospital::class)
        ->and($pedido->hemocentro)->toBeInstanceOf(Hemocentro::class)
        ->and($pedido->funcionario)->toBeInstanceOf(Funcionario::class)
        ->and($bolsa->hemocentro)->toBeInstanceOf(Hemocentro::class);
});

it('resolves the blood bag hemocenter and cast attributes', function () {
    $hemocentro = Hemocentro::factory()->create();

    $bolsa = BolsaSangue::factory()->create(['cnes_hem' => $hemocentro->cnes_hem]);

    expect($bolsa->hemocentro)->toBeInstanceOf(Hemocentro::class)
        ->and($bolsa->tipo_bolsa)->toBeInstanceOf(TipoSanguineo::class)
        ->and($bolsa->disponibilidade_bolsa)->toBe(StatusBolsa::Disponivel)
        ->and($bolsa->data_venc_bolsa)->toBeInstanceOf(Carbon::class);
});

it('returns only blood bags from the selected hemocenter', function () {
    $hemocentro = Hemocentro::factory()->create();
    $outroHemocentro = Hemocentro::factory()->create();

    $bolsa = BolsaSangue::factory()->noHemocentro($hemocentro)->create();
    BolsaSangue::factory()->noHemocentro($outroHemocentro)->create();

    expect(BolsaSangue::where('cnes_hem', $hemocentro->cnes_hem)->pluck('isbt')->all())->toBe([$bolsa->isbt]);
});

it('returns only available blood bags', function () {
    $disponivel = BolsaSangue::factory()->create();
    BolsaSangue::factory()->comDisponibilidade('reservada')->create();

    expect(BolsaSangue::disponiveis()->pluck('isbt')->all())->toBe([$disponivel->isbt]);
});

it('returns blood bags expiring within the given window', function () {
    $proxima = BolsaSangue::factory()->vencendoEm(3)->create();
    BolsaSangue::factory()->vencendoEm(30)->create();
    BolsaSangue::factory()->vencida()->create();

    expect(BolsaSangue::aVencer(7)->pluck('isbt')->all())->toBe([$proxima->isbt]);
});

it('returns expired blood bags', function () {
    $vencida = BolsaSangue::factory()->vencida()->create();
    BolsaSangue::factory()->create();

    expect(BolsaSangue::vencidas()->pluck('isbt')->all())->toBe([$vencida->isbt]);
});

it('returns only orders from the selected hospital', function () {
    $hospital = Hospital::factory()->create();
    $pedido = Pedido::factory()->create(['cnpj_hosp' => $hospital->cnpj_hosp]);
    Pedido::factory()->create();

    expect(Pedido::doHospital($hospital->cnpj_hosp)->pluck('id_ped')->all())->toBe([$pedido->id_ped]);
});

it('returns only pending orders', function () {
    $pendente = Pedido::factory()->create(['status_ped' => 'pendente']);
    Pedido::factory()->comStatusPed('entregue')->create();

    expect(Pedido::pendentes()->pluck('id_ped')->all())->toBe([$pendente->id_ped]);
});

it('keeps the order description and quantity', function () {
    $pedido = Pedido::factory()->create([
        'desc_ped' => 'UTI - paciente em observação',
        'quant_ped' => 4,
    ]);

    expect($pedido->desc_ped)->toBe('UTI - paciente em observação')
        ->and($pedido->quant_ped)->toBe(4);
});

it('rejects an order linked to an unknown hemocenter', function () {
    expect(fn () => Pedido::factory()->create(['cnes_hem' => '9999999']))->toThrow(QueryException::class);
});
