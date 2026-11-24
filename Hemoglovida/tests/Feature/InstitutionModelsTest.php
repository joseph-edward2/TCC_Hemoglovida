<?php

use App\Enums\CargoFuncionario;
use App\Enums\StatusBolsa;
use App\Enums\StatusPedido;
use App\Enums\TipoSanguineo;
use App\Enums\UrgenciaPedido;
use App\Models\BolsaSangue;
use App\Models\Funcionario;
use App\Models\Hemocentro;
use App\Models\Hospital;
use App\Models\Pedido;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('aplica os casts dos enums nos models', function () {
    $hospital = Hospital::factory()->create();

    $funcionario = Funcionario::factory()->paraHospital($hospital)->create();
    $pedido = Pedido::factory()->noHospital($hospital)->create([
        'cod_reg_fun' => $funcionario->cod_reg_fun,
        'status_ped' => StatusPedido::EmSeparacao->value,
        'urgencia_ped' => UrgenciaPedido::Urgente->value,
        'tipo_sanguineo_ped' => TipoSanguineo::OMenos->value,
    ]);
    $bolsa = BolsaSangue::factory()->noHospital($hospital)->create([
        'disponibilidade_bolsa' => StatusBolsa::EmTransito->value,
        'tipo_bolsa' => TipoSanguineo::ABMais->value,
    ]);

    expect($funcionario->tipo_fun)->toBe(CargoFuncionario::Administrativo)
        ->and($pedido->status_ped)->toBe(StatusPedido::EmSeparacao)
        ->and($pedido->urgencia_ped)->toBe(UrgenciaPedido::Urgente)
        ->and($pedido->tipo_sanguineo_ped)->toBe(TipoSanguineo::OMenos)
        ->and($bolsa->disponibilidade_bolsa)->toBe(StatusBolsa::EmTransito)
        ->and($bolsa->tipo_bolsa)->toBe(TipoSanguineo::ABMais);
});

it('expõe as relações das duas instituições', function () {
    $hospital = Hospital::factory()->create();
    $hemocentro = Hemocentro::factory()->create();

    $funcionarioHospital = Funcionario::factory()->paraHospital($hospital)->create();
    $funcionarioHemocentro = Funcionario::factory()->paraHemocentro($hemocentro)->create();
    $pedido = Pedido::factory()->noHospital($hospital)->create([
        'cnes_hem' => $hemocentro->cnes_hem,
    ]);
    $bolsa = BolsaSangue::factory()->noHospital($hospital)->create([
        'cnes_hem' => $hemocentro->cnes_hem,
    ]);

    expect($hospital->funcionarios->pluck('cod_reg_fun')->all())->toContain($funcionarioHospital->cod_reg_fun)
        ->and($hospital->pedidos->pluck('id_ped')->all())->toContain($pedido->id_ped)
        ->and($hospital->bolsas->pluck('isbt')->all())->toContain($bolsa->isbt)
        ->and($hemocentro->funcionarios->pluck('cod_reg_fun')->all())->toContain($funcionarioHemocentro->cod_reg_fun)
        ->and($hemocentro->pedidos->pluck('id_ped')->all())->toContain($pedido->id_ped)
        ->and($hemocentro->bolsas->pluck('isbt')->all())->toContain($bolsa->isbt)
        ->and($funcionarioHemocentro->instituicao())->toBeInstanceOf(Hemocentro::class);
});

it('filtra pedidos por hospital, hemocentro, pendência e criticidade', function () {
    $hospital = Hospital::factory()->create();
    $outroHospital = Hospital::factory()->create();
    $hemocentro = Hemocentro::factory()->create();
    $outroHemocentro = Hemocentro::factory()->create();

    $alvo = Pedido::factory()->noHospital($hospital)->create([
        'cnes_hem' => $hemocentro->cnes_hem,
        'status_ped' => StatusPedido::Pendente->value,
        'urgencia_ped' => UrgenciaPedido::Critica->value,
    ]);

    Pedido::factory()->noHospital($outroHospital)->create([
        'cnes_hem' => $hemocentro->cnes_hem,
        'status_ped' => StatusPedido::Pendente->value,
        'urgencia_ped' => UrgenciaPedido::Urgente->value,
    ]);
    Pedido::factory()->noHospital($hospital)->create([
        'cnes_hem' => $outroHemocentro->cnes_hem,
        'status_ped' => StatusPedido::Entregue->value,
        'urgencia_ped' => UrgenciaPedido::Rotina->value,
    ]);

    expect(Pedido::doHospital($hospital->cnpj_hosp)->pluck('id_ped')->all())->toContain($alvo->id_ped)
        ->and(Pedido::doHemocentro($hemocentro->cnes_hem)->pluck('id_ped')->all())->toContain($alvo->id_ped)
        ->and(Pedido::pendentes()->pluck('id_ped')->all())->toContain($alvo->id_ped)
        ->and(Pedido::criticos()->pluck('id_ped')->all())->toBe([$alvo->id_ped]);
});

it('filtra bolsas por hospital, trânsito, disponibilidade e vencimento', function () {
    $hospital = Hospital::factory()->create();
    $outroHospital = Hospital::factory()->create();

    $emTransito = BolsaSangue::factory()->noHospital($hospital)->emTransito()->create();
    $disponivel = BolsaSangue::factory()->noHospital($hospital)->create();
    $vencendo = BolsaSangue::factory()->noHospital($hospital)->vencendoEm(3)->create();
    $vencida = BolsaSangue::factory()->noHospital($hospital)->vencida()->create();
    $deOutro = BolsaSangue::factory()->noHospital($outroHospital)->create();

    expect(BolsaSangue::doHospital($hospital->cnpj_hosp)->pluck('isbt')->all())
        ->toContain($emTransito->isbt)->toContain($disponivel->isbt)->not->toContain($deOutro->isbt)
        ->and(BolsaSangue::emTransito()->pluck('isbt')->all())->toBe([$emTransito->isbt])
        ->and(BolsaSangue::disponiveis()->pluck('isbt')->all())->toContain($disponivel->isbt)->not->toContain($emTransito->isbt)
        ->and(BolsaSangue::aVencer()->pluck('isbt')->all())->toContain($vencendo->isbt)->not->toContain($vencida->isbt)
        ->and(BolsaSangue::vencidas()->pluck('isbt')->all())->toBe([$vencida->isbt]);
});

it('usa a configuração de estoque para metas e janela de vencimento', function () {
    expect(config('estoque.metas'))->toHaveCount(8)
        ->and(config('estoque.metas.O-'))->toBe(30)
        ->and(config('estoque.metas.O+'))->toBe(100)
        ->and(config('estoque.critico_percentual'))->toBe(25)
        ->and(config('estoque.dias_a_vencer'))->toBe(7);
});
