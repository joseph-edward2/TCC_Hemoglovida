<?php

use App\Enums\CargoFuncionario;
use App\Enums\StatusPedido;
use App\Enums\UrgenciaPedido;
use App\Models\BolsaSangue;
use App\Models\Hemocentro;
use App\Models\Hospital;
use App\Models\Pedido;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-25 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function pedidoDeTeste(Hospital $hospital, Hemocentro $hemocentro, array $atributos): Pedido
{
    return Pedido::factory()->noHospital($hospital)->create(array_merge([
        'cnes_hem' => $hemocentro->cnes_hem,
        'tipo_sanguineo_ped' => 'O-',
    ], $atributos));
}

test('o resumo conta pendentes, em separação, enviados e críticos do hospital', function () {
    $hospital = Hospital::factory()->create();
    $outroHospital = Hospital::factory()->create();
    $hemocentro = Hemocentro::factory()->create(['nome_hem' => 'Hemocentro Central']);

    foreach (range(1, 2) as $indice) {
        pedidoDeTeste($hospital, $hemocentro, ['status_ped' => 'pendente']);
    }
    pedidoDeTeste($hospital, $hemocentro, ['status_ped' => 'em_analise']);
    foreach (range(1, 3) as $indice) {
        pedidoDeTeste($hospital, $hemocentro, ['status_ped' => 'em_separacao']);
    }
    pedidoDeTeste($hospital, $hemocentro, ['status_ped' => 'enviado']);
    foreach (range(1, 3) as $indice) {
        pedidoDeTeste($hospital, $hemocentro, [
            'status_ped' => 'entregue',
            'data_hora_entrega_ped' => now()->setTime(8 + $indice, 0),
        ]);
    }
    pedidoDeTeste($hospital, $hemocentro, [
        'status_ped' => 'entregue',
        'data_hora_entrega_ped' => now()->subDay(),
    ]);
    foreach (range(1, 2) as $indice) {
        pedidoDeTeste($hospital, $hemocentro, [
            'status_ped' => 'pendente',
            'urgencia_ped' => UrgenciaPedido::Critica->value,
        ]);
    }
    pedidoDeTeste($hospital, $hemocentro, [
        'status_ped' => 'entregue',
        'urgencia_ped' => UrgenciaPedido::Critica->value,
        'data_hora_entrega_ped' => now()->subHour(),
    ]);
    pedidoDeTeste($outroHospital, $hemocentro, ['status_ped' => 'pendente']);

    $resposta = $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->getJson(route('hospital.pedidos.resumo'))
        ->assertOk();

    expect($resposta->json('pendentes'))->toBe(5)
        ->and($resposta->json('em_separacao'))->toBe(3)
        ->and($resposta->json('enviados'))->toBe(1)
        ->and($resposta->json('criticos'))->toBe(2)
        ->and($resposta->json('recebidos_hoje'))->toBeNull();
});

test('a lista traz pedidos com chegada a confirmar primeiro e nunca de outro hospital', function () {
    $hospital = Hospital::factory()->create();
    $outroHospital = Hospital::factory()->create();
    $hemocentro = Hemocentro::factory()->create(['nome_hem' => 'Hemocentro Central']);
    $funcionario = $hospital->funcionarios()->create([
        'nome_fun' => 'Funcionário',
        'senha_fun' => 'senha',
        'tipo_fun' => CargoFuncionario::Administrativo->value,
    ]);

    $enviado = pedidoDeTeste($hospital, $hemocentro, [
        'status_ped' => StatusPedido::Enviado->value,
        'data_hora_ped' => now()->subDays(5),
        'desc_ped' => 'Paciente / UTI',
        'quant_ped' => 2,
        'cod_reg_fun' => $funcionario->cod_reg_fun,
        'data_hora_envio_ped' => now()->subHours(2),
    ]);
    BolsaSangue::factory()->noHospital($hospital)->noHemocentro($hemocentro)->emTransito()->create([
        'id_ped' => $enviado->id_ped,
        'tipo_bolsa' => 'O-',
    ]);

    $recente = pedidoDeTeste($hospital, $hemocentro, [
        'status_ped' => 'pendente',
        'data_hora_ped' => now()->subHour(),
        'desc_ped' => 'Paciente / Emergência',
    ]);

    pedidoDeTeste($outroHospital, $hemocentro, [
        'status_ped' => StatusPedido::Enviado->value,
        'data_hora_ped' => now(),
    ]);

    $resposta = $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->getJson(route('hospital.pedidos.listar'))
        ->assertOk();

    expect($resposta->json('data.0.id_ped'))->toBe($enviado->id_ped)
        ->and($resposta->json('data.0.pode_confirmar_chegada'))->toBeTrue()
        ->and($resposta->json('data.0.bolsas_em_transito'))->toBe(1)
        ->and($resposta->json('data.0.etapa'))->toBe(4)
        ->and($resposta->json('data.0.hemocentro.rotulo'))->toBe('Hemocentro Central')
        ->and($resposta->json('data.1.id_ped'))->toBe($recente->id_ped)
        ->and($resposta->json('data.1.pode_confirmar_chegada'))->toBeFalse()
        ->and($resposta->json('meta.total'))->toBe(2);
});

test('a lista não faz uma consulta por pedido e limita a página a cinquenta itens', function () {
    $hospital = Hospital::factory()->create();
    $hemocentro = Hemocentro::factory()->create();

    foreach (range(1, 6) as $indice) {
        pedidoDeTeste($hospital, $hemocentro, [
            'data_hora_ped' => now()->subMinutes($indice),
        ]);
    }

    DB::enableQueryLog();
    DB::flushQueryLog();

    $resposta = $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->getJson(route('hospital.pedidos.listar', ['por_pagina' => 100]))
        ->assertOk();

    $consultas = collect(DB::getQueryLog())->count();
    DB::disableQueryLog();

    expect($resposta->json('meta.per_page'))->toBe(50)
        ->and($consultas)->toBeLessThan(20);
});

test('a lista respeita o parâmetro pagina e devolve pedidos diferentes', function () {
    $hospital = Hospital::factory()->create();
    $hemocentro = Hemocentro::factory()->create();
    $pedidos = collect();

    foreach (range(1, 3) as $indice) {
        $pedidos->push(pedidoDeTeste($hospital, $hemocentro, [
            'data_hora_ped' => now()->subMinutes($indice),
        ]));
    }

    $padrao = $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->getJson(route('hospital.pedidos.listar'))
        ->assertOk();

    $primeira = $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->getJson(route('hospital.pedidos.listar', ['por_pagina' => 2, 'pagina' => 1]))
        ->assertOk();

    $segunda = $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->getJson(route('hospital.pedidos.listar', ['por_pagina' => 2, 'pagina' => 2]))
        ->assertOk();

    $idsPrimeira = collect($primeira->json('data'))->pluck('id_ped')->all();
    $idsSegunda = collect($segunda->json('data'))->pluck('id_ped')->all();

    expect($padrao->json('meta.per_page'))->toBe(5)
        ->and($primeira->json('meta.current_page'))->toBe(1)
        ->and($primeira->json('meta.per_page'))->toBe(2)
        ->and($primeira->json('meta.total'))->toBe(3)
        ->and($segunda->json('meta.current_page'))->toBe(2)
        ->and($idsSegunda)->toHaveCount(1)
        ->and(array_intersect($idsPrimeira, $idsSegunda))->toBe([])
        ->and($idsSegunda[0])->toBe($pedidos->last()->id_ped);
});

test('bolsa em trânsito de outro hospital não habilita a confirmação', function () {
    $hospital = Hospital::factory()->create();
    $outroHospital = Hospital::factory()->create();
    $hemocentro = Hemocentro::factory()->create();

    $pedido = pedidoDeTeste($hospital, $hemocentro, ['status_ped' => 'enviado']);
    BolsaSangue::factory()->noHospital($outroHospital)->emTransito()->create([
        'id_ped' => $pedido->id_ped,
    ]);

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->getJson(route('hospital.pedidos.listar'))
        ->assertOk()
        ->assertJsonPath('data.0.pode_confirmar_chegada', false)
        ->assertJsonPath('data.0.bolsas_em_transito', 0);
});

test('a lista expõe o formato completo de um pedido', function () {
    $hospital = Hospital::factory()->create();
    $hemocentro = Hemocentro::factory()->create(['nome_hem' => 'Hemocentro Leste']);
    $pedido = pedidoDeTeste($hospital, $hemocentro, [
        'desc_ped' => '[DEMO] Maria S. - UTI',
        'quant_ped' => 4,
        'urgencia_ped' => UrgenciaPedido::Urgente->value,
        'status_ped' => 'em_analise',
        'url_requisicao_transfusao_ped' => 'requisicoes/pdf.pdf',
    ]);
    BolsaSangue::factory()->noHospital($hospital)->create([
        'id_ped' => $pedido->id_ped,
        'data_hora_recebimento_bolsa' => now()->subHour(),
    ]);

    $resposta = $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->getJson(route('hospital.pedidos.listar'))
        ->assertOk();

    expect($resposta->json('data.0'))->toMatchArray([
        'id_ped' => $pedido->id_ped,
        'tipo_sanguineo_ped' => 'O-',
        'quant_ped' => 4,
        'urgencia_ped' => 'urgente',
        'urgencia_label' => 'Urgente',
        'status_ped' => 'em_analise',
        'status_label' => 'Em análise',
        'etapa' => 2,
        'desc_ped' => '[DEMO] Maria S. - UTI',
        'tem_requisicao' => true,
        'bolsas_recebidas' => 1,
    ]);
});
