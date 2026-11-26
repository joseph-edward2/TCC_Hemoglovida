<?php

use App\Enums\StatusBolsa;
use App\Enums\StatusPedido;
use App\Models\BolsaSangue;
use App\Models\Hemocentro;
use App\Models\Hospital;
use App\Models\Pedido;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-25 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function pedidoEnviado(Hospital $hospital, Hemocentro $hemocentro, int $quantidade): Pedido
{
    return Pedido::factory()->noHospital($hospital)->create([
        'cnes_hem' => $hemocentro->cnes_hem,
        'status_ped' => StatusPedido::Enviado->value,
        'quant_ped' => $quantidade,
        'tipo_sanguineo_ped' => 'O-',
        'data_hora_envio_ped' => now()->subHours(2),
    ]);
}

function bolsaEmTransito(Hospital $hospital, Hemocentro $hemocentro, Pedido $pedido, string $isbt): BolsaSangue
{
    return BolsaSangue::factory()->noHospital($hospital)->noHemocentro($hemocentro)->emTransito()->create([
        'isbt' => $isbt,
        'id_ped' => $pedido->id_ped,
        'tipo_bolsa' => 'O-',
        'data_hora_envio_bolsa' => now()->subHours(2),
    ]);
}

test('confirmar chegada entrega as bolsas ao estoque e finaliza o pedido', function () {
    $hospital = Hospital::factory()->create();
    $hemocentro = Hemocentro::factory()->create();
    $pedido = pedidoEnviado($hospital, $hemocentro, 3);
    $bolsas = collect([
        bolsaEmTransito($hospital, $hemocentro, $pedido, 'W000026000001'),
        bolsaEmTransito($hospital, $hemocentro, $pedido, 'W000026000002'),
        bolsaEmTransito($hospital, $hemocentro, $pedido, 'W000026000003'),
    ]);

    $resposta = $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->postJson(route('hospital.pedidos.confirmar-chegada', $pedido))
        ->assertOk();

    expect($resposta->json('bolsas_adicionadas'))->toBe(3)
        ->and($resposta->json('pedido.status_ped'))->toBe('entregue')
        ->and($pedido->refresh()->data_hora_entrega_ped?->toISOString())->toBe(now()->toISOString());

    foreach ($bolsas as $bolsa) {
        $bolsa->refresh();
        expect($bolsa->disponibilidade_bolsa)->toBe(StatusBolsa::Disponivel)
            ->and($bolsa->data_hora_recebimento_bolsa?->toISOString())->toBe(now()->toISOString());
    }

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->getJson(route('hospital.estoque.resumo'))
        ->assertOk()
        ->assertJsonPath('cards.0.quantidade', 3);
});

test('entrega parcial mantém o pedido enviado e informa as bolsas recebidas', function () {
    $hospital = Hospital::factory()->create();
    $hemocentro = Hemocentro::factory()->create();
    $pedido = pedidoEnviado($hospital, $hemocentro, 5);

    bolsaEmTransito($hospital, $hemocentro, $pedido, 'W000026000001');
    bolsaEmTransito($hospital, $hemocentro, $pedido, 'W000026000002');
    BolsaSangue::factory()->noHospital($hospital)->noHemocentro($hemocentro)->create([
        'isbt' => 'W000026000003',
        'id_ped' => $pedido->id_ped,
        'tipo_bolsa' => 'O-',
        'data_hora_recebimento_bolsa' => now()->subHour(),
    ]);

    $resposta = $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->postJson(route('hospital.pedidos.confirmar-chegada', $pedido))
        ->assertOk();

    expect($resposta->json('bolsas_adicionadas'))->toBe(2)
        ->and($resposta->json('bolsas_recebidas'))->toBe(3)
        ->and($resposta->json('entrega_parcial'))->toBeTrue()
        ->and($resposta->json('pedido.status_ped'))->toBe('enviado')
        ->and($pedido->refresh()->data_hora_entrega_ped)->toBeNull();
});

test('confirmar sem bolsas em trânsito e repetir a chamada retorna 409 sem duplicar entradas', function () {
    $hospital = Hospital::factory()->create();
    $hemocentro = Hemocentro::factory()->create();
    $pedido = pedidoEnviado($hospital, $hemocentro, 1);
    bolsaEmTransito($hospital, $hemocentro, $pedido, 'W000026000001');

    $rota = route('hospital.pedidos.confirmar-chegada', $pedido);

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->postJson($rota)
        ->assertOk()
        ->assertJsonPath('bolsas_adicionadas', 1);

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->postJson($rota)
        ->assertConflict()
        ->assertJsonPath('message', 'Não há bolsas em trânsito para confirmar.');

    expect(BolsaSangue::where('id_ped', $pedido->id_ped)->count())->toBe(1)
        ->and(BolsaSangue::where('id_ped', $pedido->id_ped)->disponiveis()->count())->toBe(1);
});

test('pedido que não está enviado retorna 422 mesmo com bolsa em trânsito', function () {
    $hospital = Hospital::factory()->create();
    $hemocentro = Hemocentro::factory()->create();
    $pedido = Pedido::factory()->noHospital($hospital)->create([
        'cnes_hem' => $hemocentro->cnes_hem,
        'status_ped' => 'pendente',
        'quant_ped' => 1,
    ]);
    $bolsa = bolsaEmTransito($hospital, $hemocentro, $pedido, 'W000026000001');

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->postJson(route('hospital.pedidos.confirmar-chegada', $pedido))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Só é possível confirmar a chegada de um pedido enviado.');

    expect($bolsa->refresh()->disponibilidade_bolsa)->toBe(StatusBolsa::EmTransito);
});

test('bolsas de outros pedidos e de outro hospital ficam intactas', function () {
    $hospital = Hospital::factory()->create();
    $outroHospital = Hospital::factory()->create();
    $hemocentro = Hemocentro::factory()->create();
    $pedido = pedidoEnviado($hospital, $hemocentro, 1);
    $outroPedido = pedidoEnviado($hospital, $hemocentro, 1);

    bolsaEmTransito($hospital, $hemocentro, $pedido, 'W000026000001');
    $deOutroPedido = bolsaEmTransito($hospital, $hemocentro, $outroPedido, 'W000026000002');
    $deOutroHospital = bolsaEmTransito($outroHospital, $hemocentro, $pedido, 'W000026000003');

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->postJson(route('hospital.pedidos.confirmar-chegada', $pedido))
        ->assertOk();

    expect($deOutroPedido->refresh()->disponibilidade_bolsa)->toBe(StatusBolsa::EmTransito)
        ->and($deOutroHospital->refresh()->disponibilidade_bolsa)->toBe(StatusBolsa::EmTransito);
});

test('a confirmação de outro hospital retorna 404', function () {
    $hospital = Hospital::factory()->create();
    $outroHospital = Hospital::factory()->create();
    $hemocentro = Hemocentro::factory()->create();
    $pedido = pedidoEnviado($outroHospital, $hemocentro, 1);

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->postJson(route('hospital.pedidos.confirmar-chegada', $pedido))
        ->assertNotFound();
});

test('a lista de bolsas em trânsito traz isbt formatado, tipo, validade e origem', function () {
    $hospital = Hospital::factory()->create();
    $hemocentro = Hemocentro::factory()->create(['nome_hem' => 'Hemocentro Central']);
    $pedido = pedidoEnviado($hospital, $hemocentro, 1);
    $bolsa = bolsaEmTransito($hospital, $hemocentro, $pedido, 'W000026000001');

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->getJson(route('hospital.pedidos.bolsas-em-transito', $pedido))
        ->assertOk()
        ->assertJsonPath('0.isbt_formatado', 'W0000 26 000001')
        ->assertJsonPath('0.tipo_bolsa', 'O-')
        ->assertJsonPath('0.data_venc_bolsa', $bolsa->data_venc_bolsa->toDateString())
        ->assertJsonPath('0.origem', 'Hemocentro Central');
});
