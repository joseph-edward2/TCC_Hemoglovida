<?php

use App\Models\Hospital;
use App\Models\Pedido;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-25 12:00:00');
    Storage::fake('local');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function pedidoComPdf(Hospital $hospital, ?string $caminho = 'requisicoes/pdf.pdf'): Pedido
{
    return Pedido::factory()->noHospital($hospital)->create([
        'url_requisicao_transfusao_ped' => $caminho,
    ]);
}

test('o dono do pedido recebe o pdf inline com headers seguros', function () {
    $hospital = Hospital::factory()->create();
    $pedido = pedidoComPdf($hospital);
    Storage::disk('local')->put($pedido->url_requisicao_transfusao_ped, "%PDF-1.4\nTESTE");

    $resposta = $this->actingAs($hospital, 'hospital')
        ->get(route('hospital.pedidos.requisicao', $pedido))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($resposta->getContent())->toBe("%PDF-1.4\nTESTE");
});

test('o pdf de outro hospital retorna 404', function () {
    $hospital = Hospital::factory()->create();
    $outroHospital = Hospital::factory()->create();
    $pedido = pedidoComPdf($outroHospital);
    Storage::disk('local')->put($pedido->url_requisicao_transfusao_ped, '%PDF-1.4');

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->get(route('hospital.pedidos.requisicao', $pedido))
        ->assertNotFound();
});

test('pedido sem arquivo retorna 404', function () {
    $hospital = Hospital::factory()->create();
    $pedido = pedidoComPdf($hospital, 'requisicoes/inexistente.pdf');

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->get(route('hospital.pedidos.requisicao', $pedido))
        ->assertNotFound();
});

test('sem sessão o pdf retorna 401 em json', function () {
    $hospital = Hospital::factory()->create();
    $pedido = pedidoComPdf($hospital);
    Storage::disk('local')->put($pedido->url_requisicao_transfusao_ped, '%PDF-1.4');

    $this->withHeader('Accept', 'application/json')
        ->get(route('hospital.pedidos.requisicao', $pedido))
        ->assertUnauthorized();
});
