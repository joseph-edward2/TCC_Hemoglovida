<?php

use App\Enums\StatusBolsa;
use App\Enums\TipoSanguineo;
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

test('o resumo traz sempre os oito tipos e conta só bolsas disponíveis não vencidas do hospital', function () {
    $hospital = Hospital::factory()->create(['nome_hosp' => 'Hospital Santa Casa']);
    $outroHospital = Hospital::factory()->create();

    foreach (TipoSanguineo::cases() as $tipo) {
        BolsaSangue::factory()->noHospital($hospital)->create([
            'tipo_bolsa' => $tipo->value,
            'quant_bolsa' => 1,
            'data_venc_bolsa' => today()->addDays(30),
        ]);
    }

    // Cada linha é uma bolsa, independentemente do valor de quant_bolsa.
    foreach ([2, 0] as $quantidade) {
        BolsaSangue::factory()->noHospital($hospital)->create([
            'tipo_bolsa' => TipoSanguineo::OMenos->value,
            'quant_bolsa' => $quantidade,
            'data_venc_bolsa' => today()->addDays(30),
        ]);
    }

    foreach ([
        StatusBolsa::Reservada,
        StatusBolsa::Utilizada,
        StatusBolsa::Descartada,
        StatusBolsa::EmTransito,
    ] as $status) {
        BolsaSangue::factory()->noHospital($hospital)->create([
            'tipo_bolsa' => TipoSanguineo::ABMais->value,
            'disponibilidade_bolsa' => $status->value,
            'data_venc_bolsa' => today()->addDays(30),
        ]);
    }

    BolsaSangue::factory()->noHospital($hospital)->vencida()->create([
        'tipo_bolsa' => TipoSanguineo::ABMais->value,
    ]);

    BolsaSangue::factory()->noHospital($outroHospital)->create([
        'tipo_bolsa' => TipoSanguineo::ABMais->value,
        'data_venc_bolsa' => today()->addDays(30),
    ]);

    $resposta = $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->get(route('hospital.estoque.resumo'))
        ->assertOk()
        ->assertJsonPath('hospital.nome', 'Hospital Santa Casa');

    $cards = collect($resposta->json('cards'))->keyBy('tipo_bolsa');

    expect($cards)->toHaveCount(8)
        ->and($cards->keys()->all())->toBe(array_map(fn (TipoSanguineo $tipo): string => $tipo->value, TipoSanguineo::cases()))
        ->and($cards->get('O-')['quantidade'])->toBe(3)
        ->and($cards->get('AB+')['quantidade'])->toBe(1);
});

test('o resumo calcula meta, percentual arredondado e criticidade pela configuração', function () {
    $hospital = Hospital::factory()->create();

    // 30% de O- (meta 30) -> 9 bolsas, acima do limite de 25%.
    foreach (range(1, 9) as $indice) {
        BolsaSangue::factory()->noHospital($hospital)->create([
            'isbt' => sprintf('W000026%06d', $indice),
            'tipo_bolsa' => TipoSanguineo::OMenos->value,
            'data_venc_bolsa' => today()->addDays(30),
        ]);
    }

    // 23% de B+ (meta 60) -> 14 bolsas, crítico sem arredondar para 25%.
    foreach (range(10, 23) as $indice) {
        BolsaSangue::factory()->noHospital($hospital)->create([
            'isbt' => sprintf('W000026%06d', $indice),
            'tipo_bolsa' => TipoSanguineo::BMais->value,
            'data_venc_bolsa' => today()->addDays(30),
        ]);
    }

    $resposta = $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->get(route('hospital.estoque.resumo'))
        ->assertOk();

    $cards = collect($resposta->json('cards'))->keyBy('tipo_bolsa');

    expect($cards->get('O-'))->toMatchArray([
        'meta' => 30,
        'percentual' => 30,
        'critico' => false,
    ])
        ->and($cards->get('B+'))->toMatchArray([
            'meta' => 60,
            'percentual' => 23,
            'critico' => true,
        ])
        ->and($resposta->json('total_criticos'))->toBe(7)
        ->and($resposta->json('tipos_criticos'))->toContain('B+')
        ->and($resposta->json('tipos_criticos'))->not->toContain('O-')
        ->and($resposta->json('atualizado_em'))->not->toBeNull();
});

test('a_vencer inclui hoje e hoje mais sete dias, mas exclui o oitavo dia', function () {
    $hospital = Hospital::factory()->create();

    foreach ([0, 1, 7] as $indice => $dias) {
        BolsaSangue::factory()->noHospital($hospital)->vencendoEm($dias)->create([
            'isbt' => sprintf('W000026%06d', $indice + 1),
            'tipo_bolsa' => TipoSanguineo::AMais->value,
        ]);
    }

    BolsaSangue::factory()->noHospital($hospital)->vencendoEm(8)->create([
        'isbt' => 'W000026000004',
        'tipo_bolsa' => TipoSanguineo::AMais->value,
    ]);

    BolsaSangue::factory()->noHospital($hospital)->vencida()->create([
        'tipo_bolsa' => TipoSanguineo::AMais->value,
    ]);

    $resposta = $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->get(route('hospital.estoque.resumo'))
        ->assertOk();

    $cards = collect($resposta->json('cards'))->keyBy('tipo_bolsa');

    expect($cards->get('A+')['a_vencer'])->toBe(3)
        ->and($cards->get('A+')['quantidade'])->toBe(4);
});
