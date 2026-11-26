<?php

use App\Models\BolsaSangue;
use App\Models\Hemocentro;
use App\Models\Hospital;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-25 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function hemocentrosComEstoque(): array
{
    $central = Hemocentro::factory()->create([
        'cnes_hem' => '0000001',
        'nome_hem' => 'Hemocentro Central',
        'endereco_hem' => 'Rua Central, 100',
        'contato_hem' => '4000-0001',
    ]);
    $leste = Hemocentro::factory()->create([
        'cnes_hem' => '0000002',
        'nome_hem' => 'Hemocentro Leste',
        'endereco_hem' => 'Avenida Leste, 200',
        'contato_hem' => '4000-0002',
    ]);
    $vazio = Hemocentro::factory()->create(['cnes_hem' => '0000003', 'nome_hem' => 'Hemocentro Norte']);

    foreach (range(1, 3) as $indice) {
        BolsaSangue::factory()->noHemocentro($central)->create([
            'isbt' => sprintf('H000026%06d', $indice),
            'tipo_bolsa' => 'O-',
        ]);
    }

    $bolsaLeste = BolsaSangue::factory()->noHemocentro($leste)->create([
        'isbt' => 'H000026000010',
        'tipo_bolsa' => 'O-',
    ]);

    BolsaSangue::factory()->noHemocentro($leste)->vencida()->create([
        'isbt' => 'H000026000011',
        'tipo_bolsa' => 'O-',
    ]);
    BolsaSangue::factory()->noHemocentro($leste)->comDisponibilidade('reservada')->create([
        'isbt' => 'H000026000012',
        'tipo_bolsa' => 'O-',
    ]);
    $bolsaLeste->update(['cnpj_hosp' => Hospital::factory()->create()->cnpj_hosp]);

    return [$central, $leste, $vazio];
}

test('a situação do estoque distingue disponível, baixo e sem estoque', function () {
    $hospital = Hospital::factory()->create();
    hemocentrosComEstoque();

    $resposta = $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->getJson(route('hospital.pedidos.hemocentros', ['tipo' => 'O-', 'quantidade' => 2]))
        ->assertOk();

    $porCnes = collect($resposta->json())->keyBy('cnes_hem');

    expect($porCnes->get('0000001')['rotulo'])->toBe('Hemocentro Central')
        ->and($porCnes->get('0000001')['cnes_hem'])->toBe('0000001')
        ->and($porCnes->get('0000001')['endereco'])->toBe('Rua Central, 100')
        ->and($porCnes->get('0000001')['contato'])->toBe('4000-0001')
        ->and($porCnes->get('0000001')['estoque'])->toBe(['quantidade' => 3, 'situacao' => 'Estoque Disponível'])
        ->and($porCnes->get('0000002')['estoque'])->toBe(['quantidade' => 0, 'situacao' => 'Sem estoque'])
        ->and($porCnes->get('0000003')['estoque'])->toBe(['quantidade' => 0, 'situacao' => 'Sem estoque']);
});

test('a situação baixa aparece quando há menos bolsas que o pedido', function () {
    $hospital = Hospital::factory()->create();
    [$central, $leste] = hemocentrosComEstoque();

    BolsaSangue::factory()->noHemocentro($leste)->create([
        'isbt' => 'H000026000020',
        'tipo_bolsa' => 'O-',
    ]);

    $resposta = $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->getJson(route('hospital.pedidos.hemocentros', ['tipo' => 'O-', 'quantidade' => 2]))
        ->assertOk();

    $porCnes = collect($resposta->json())->keyBy('cnes_hem');

    expect($porCnes->get('0000002')['estoque'])->toBe(['quantidade' => 1, 'situacao' => 'Estoque Baixo']);
});

test('sem tipo escolhido a situação não é exibida', function () {
    $hospital = Hospital::factory()->create();
    hemocentrosComEstoque();

    $resposta = $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->getJson(route('hospital.pedidos.hemocentros'))
        ->assertOk();

    expect($resposta->json('0.estoque.quantidade'))->toBe(0)
        ->and($resposta->json('0.estoque.situacao'))->toBeNull();
});
