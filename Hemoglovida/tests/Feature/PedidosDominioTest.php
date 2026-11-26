<?php

use App\Enums\StatusBolsa;
use App\Enums\StatusPedido;
use App\Models\BolsaSangue;
use App\Models\Hemocentro;
use App\Models\Hospital;
use App\Services\EstoqueHospitalService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-25 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('o rótulo do hemocentro usa o nome cadastrado e faz fallback pelo cnes', function () {
    $central = Hemocentro::factory()->create(['cnes_hem' => '0000001', 'nome_hem' => 'Hemocentro Central']);
    $leste = Hemocentro::factory()->create(['cnes_hem' => '0000002', 'nome_hem' => null]);
    $outro = Hemocentro::factory()->create(['cnes_hem' => '1234567', 'nome_hem' => null]);

    expect($central->rotulo)->toBe('Hemocentro Central')
        ->and($leste->rotulo)->toBe('Hemocentro Leste')
        ->and($outro->rotulo)->toBe('Hemocentro 1234567');
});

test('a origem exibida na bolsa usa o rótulo do hemocentro', function () {
    $hospital = Hospital::factory()->create();
    $hemocentro = Hemocentro::factory()->create([
        'cnes_hem' => '0000001',
        'nome_hem' => 'Hemocentro Central',
    ]);
    $bolsa = BolsaSangue::factory()->noHospital($hospital)->noHemocentro($hemocentro)->create();

    expect(EstoqueHospitalService::origem($bolsa->load('hemocentro')))->toBe('Hemocentro Central');
});

test('cada status de pedido expõe a etapa correspondente da timeline', function () {
    expect(StatusPedido::Pendente->etapa())->toBe(1)
        ->and(StatusPedido::EmAnalise->etapa())->toBe(2)
        ->and(StatusPedido::EmSeparacao->etapa())->toBe(3)
        ->and(StatusPedido::Enviado->etapa())->toBe(4)
        ->and(StatusPedido::Entregue->etapa())->toBe(5);
});

test('a transição da bolsa é aplicada por um único método de domínio', function () {
    $hospital = Hospital::factory()->create();
    $bolsa = BolsaSangue::factory()->noHospital($hospital)->emTransito()->create([
        'data_hora_envio_bolsa' => now()->subHours(2),
    ]);

    $bolsa->aplicarTransicao(StatusBolsa::Disponivel);

    expect($bolsa->disponibilidade_bolsa)->toBe(StatusBolsa::Disponivel)
        ->and($bolsa->data_hora_recebimento_bolsa?->toISOString())->toBe(now()->toISOString());

    $bolsa->aplicarTransicao(StatusBolsa::Utilizada, 'Paciente / UTI');

    expect($bolsa->disponibilidade_bolsa)->toBe(StatusBolsa::Utilizada)
        ->and($bolsa->data_hora_saida_bolsa?->toISOString())->toBe(now()->toISOString())
        ->and($bolsa->paciente_destino_bolsa)->toBe('Paciente / UTI');
});
