<?php

use App\Enums\StatusBolsa;
use App\Models\BolsaSangue;
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

test('o consumo separa quatro semanas e respeita os limites exatos de cada semana', function () {
    $hospital = Hospital::factory()->create();
    $outroHospital = Hospital::factory()->create();

    /** @var array<int, array{inicio: Carbon, fim: Carbon}> $semanas */
    $semanas = collect(range(0, 3))->map(function (int $indice): array {
        $inicio = today()->subDays(27 - (7 * $indice))->startOfDay();

        return ['inicio' => $inicio, 'fim' => $inicio->copy()->addDays(6)->endOfDay()];
    })->all();

    $criar = function (Hospital $dono, Carbon $recebimento, ?Carbon $saida = null, StatusBolsa $status = StatusBolsa::Utilizada): void {
        BolsaSangue::factory()->noHospital($dono)->create([
            'disponibilidade_bolsa' => $status->value,
            'data_hora_recebimento_bolsa' => $recebimento,
            'data_hora_saida_bolsa' => $saida,
        ]);
    };

    // Semana 1: uma entrada e uma saída no limite inicial.
    $criar($hospital, $semanas[0]['inicio'], $semanas[0]['inicio']);

    // Semana 2: entradas nos dois limites; saída descartada no limite final.
    $criar($hospital, $semanas[1]['inicio']);
    $criar($hospital, $semanas[1]['fim'], $semanas[1]['fim'], StatusBolsa::Descartada);

    // Semana 3: entrada e saída apenas no limite final.
    $criar($hospital, $semanas[2]['fim'], $semanas[2]['fim'], StatusBolsa::Descartada);

    // Semana 4: três entradas e duas saídas.
    $criar($hospital, $semanas[3]['inicio'], $semanas[3]['inicio']);
    $criar($hospital, $semanas[3]['inicio']->copy()->addDays(3));
    $criar($hospital, $semanas[3]['fim'], $semanas[3]['inicio']->copy()->addDays(3), StatusBolsa::Descartada);

    // Fora do período e de outro hospital: não entram no gráfico.
    $criar($hospital, $semanas[0]['inicio']->copy()->subSecond());
    $criar($hospital, $semanas[3]['fim']->copy()->addSecond());
    $criar($outroHospital, now(), now());

    $resposta = $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->get(route('hospital.estoque.consumo'))
        ->assertOk()
        ->assertJsonPath('periodo.inicio', $semanas[0]['inicio']->toISOString())
        ->assertJsonPath('periodo.fim', $semanas[3]['fim']->toISOString());

    expect($resposta->json('semanas'))->toHaveCount(4)
        ->and($resposta->json('semanas.0'))->toMatchArray([
            'rotulo' => 'SEMANA 1',
            'entradas' => 1,
            'saidas' => 1,
            'utilizadas' => 1,
            'descartadas' => 0,
        ])
        ->and($resposta->json('semanas.1'))->toMatchArray([
            'rotulo' => 'SEMANA 2',
            'entradas' => 2,
            'saidas' => 1,
            'utilizadas' => 0,
            'descartadas' => 1,
        ])
        ->and($resposta->json('semanas.2'))->toMatchArray([
            'rotulo' => 'SEMANA 3',
            'entradas' => 1,
            'saidas' => 1,
            'utilizadas' => 0,
            'descartadas' => 1,
        ])
        ->and($resposta->json('semanas.3'))->toMatchArray([
            'rotulo' => 'SEMANA 4',
            'entradas' => 3,
            'saidas' => 2,
            'utilizadas' => 1,
            'descartadas' => 1,
        ])
        ->and($resposta->json('escala_max'))->toBe(20);
});

test('a escala do gráfico arredonda para cima em múltiplos de vinte', function () {
    $hospital = Hospital::factory()->create();

    foreach (range(1, 43) as $indice) {
        BolsaSangue::factory()->noHospital($hospital)->create([
            'data_hora_recebimento_bolsa' => now()->subHours($indice),
        ]);
    }

    $resposta = $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->get(route('hospital.estoque.consumo'))
        ->assertOk();

    expect($resposta->json('escala_max'))->toBe(60);
});

test('semanas sem movimentação retornam zero', function () {
    $hospital = Hospital::factory()->create();

    $resposta = $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->get(route('hospital.estoque.consumo'))
        ->assertOk();

    expect($resposta->json('semanas'))->toBe([
        ['rotulo' => 'SEMANA 1', 'entradas' => 0, 'saidas' => 0, 'utilizadas' => 0, 'descartadas' => 0],
        ['rotulo' => 'SEMANA 2', 'entradas' => 0, 'saidas' => 0, 'utilizadas' => 0, 'descartadas' => 0],
        ['rotulo' => 'SEMANA 3', 'entradas' => 0, 'saidas' => 0, 'utilizadas' => 0, 'descartadas' => 0],
        ['rotulo' => 'SEMANA 4', 'entradas' => 0, 'saidas' => 0, 'utilizadas' => 0, 'descartadas' => 0],
    ])
        ->and($resposta->json('escala_max'))->toBe(20);
});
