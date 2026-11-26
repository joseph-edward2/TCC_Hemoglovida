<?php

use App\Enums\StatusBolsa;
use App\Models\BolsaSangue;
use App\Models\Hemocentro;
use App\Models\Hospital;
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

test('a lista de bolsas é paginada, ordenada e traz origem, vencimento e formato do ISBT', function () {
    $hospital = Hospital::factory()->create();
    $outroHospital = Hospital::factory()->create();
    $hemocentro = Hemocentro::factory()->create(['cnes_hem' => '0000001']);

    $emTransito = BolsaSangue::factory()
        ->noHospital($hospital)
        ->noHemocentro($hemocentro)
        ->emTransito()
        ->create([
            'isbt' => 'W000026123456',
            'tipo_bolsa' => 'O-',
        ]);

    foreach (range(1, 24) as $indice) {
        BolsaSangue::factory()->noHospital($hospital)->create([
            'data_hora_recebimento_bolsa' => now()->subDays($indice),
            'data_venc_bolsa' => $indice === 1 ? today()->subDay() : today()->addDays(30),
        ]);
    }

    BolsaSangue::factory()->noHospital($outroHospital)->create();

    DB::enableQueryLog();

    $resposta = $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->get(route('hospital.estoque.bolsas', ['por_pagina' => 20]))
        ->assertOk()
        ->assertJsonPath('meta.total', 25)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('data.0.isbt', $emTransito->isbt)
        ->assertJsonPath('data.0.isbt_formatado', 'W0000 26 123456')
        ->assertJsonPath('data.0.origem', 'Hemocentro Central')
        ->assertJsonPath('data.0.disponibilidade_bolsa', StatusBolsa::EmTransito->value)
        ->assertJsonPath('data.0.disponibilidade_label', 'Em trânsito');

    $consultasHemocentro = collect(DB::getQueryLog())
        ->filter(fn (array $consulta): bool => str_contains($consulta['query'], 'from "hemocentro"'))
        ->count();

    DB::disableQueryLog();

    expect($resposta->json('data'))->toHaveCount(20)
        ->and($consultasHemocentro)->toBe(1)
        ->and(collect($resposta->json('data'))->pluck('isbt'))->not->toContain('H-9999');
});

test('a segunda página devolve apenas o restante e respeita o limite de cem por página', function () {
    $hospital = Hospital::factory()->create();

    foreach (range(1, 25) as $indice) {
        BolsaSangue::factory()->noHospital($hospital)->create([
            'isbt' => sprintf('W000026%06d', $indice),
        ]);
    }

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->get(route('hospital.estoque.bolsas', ['pagina' => 2, 'por_pagina' => 20]))
        ->assertOk()
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonCount(5, 'data');

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->get(route('hospital.estoque.bolsas', ['por_pagina' => 500]))
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);
});

test('a lista marca bolsas vencidas', function () {
    $hospital = Hospital::factory()->create();

    $vencida = BolsaSangue::factory()->noHospital($hospital)->vencida()->create();

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->get(route('hospital.estoque.bolsas'))
        ->assertOk()
        ->assertJsonPath('data.0.isbt', $vencida->isbt)
        ->assertJsonPath('data.0.vencida', true);
});

test('o detalhe traz envio, paciente e transições permitidas', function () {
    $hospital = Hospital::factory()->create();

    $bolsa = BolsaSangue::factory()->noHospital($hospital)->create([
        'data_hora_envio_bolsa' => now()->subDays(2),
        'paciente_destino_bolsa' => 'Paciente / UTI',
    ]);

    $resposta = $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->get(route('hospital.estoque.bolsa', $bolsa->isbt))
        ->assertOk();

    $resposta->assertJsonPath('data.paciente_destino_bolsa', 'Paciente / UTI')
        ->assertJsonPath('data.data_hora_envio_bolsa', $bolsa->data_hora_envio_bolsa->toISOString())
        ->assertJsonPath('estado_final', false);

    expect($resposta->json('status_permitidos'))->toBe([
        ['valor' => 'reservada', 'rotulo' => 'Reservada'],
        ['valor' => 'utilizada', 'rotulo' => 'Utilizada'],
        ['valor' => 'descartada', 'rotulo' => 'Descartada'],
    ]);
});

test('o detalhe de uma bolsa de outro hospital retorna 404', function () {
    $hospital = Hospital::factory()->create();
    $outroHospital = Hospital::factory()->create();

    $bolsa = BolsaSangue::factory()->noHospital($outroHospital)->create();

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->get(route('hospital.estoque.bolsa', $bolsa->isbt))
        ->assertNotFound();
});

test('a origem usa nome_hem quando o hemocentro possui nome cadastrado', function () {
    $hospital = Hospital::factory()->create();
    $hemocentro = Hemocentro::factory()->create(['nome_hem' => 'Hemocentro Guarulhos']);

    $bolsa = BolsaSangue::factory()
        ->noHospital($hospital)
        ->noHemocentro($hemocentro)
        ->create();

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->get(route('hospital.estoque.bolsas'))
        ->assertOk()
        ->assertJsonPath('data.0.origem', 'Hemocentro Guarulhos');
});
