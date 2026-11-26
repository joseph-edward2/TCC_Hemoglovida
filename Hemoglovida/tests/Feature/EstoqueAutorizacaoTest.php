<?php

use App\Models\BolsaSangue;
use App\Models\Funcionario;
use App\Models\Hemocentro;
use App\Models\Hospital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
});

test('visitante sem sessão recebe 401 em JSON nos endpoints do estoque', function () {
    $bolsa = BolsaSangue::factory()->create();
    $urls = [
        route('hospital.estoque.resumo'),
        route('hospital.estoque.consumo'),
        route('hospital.estoque.bolsas'),
        route('hospital.estoque.bolsa', $bolsa->isbt),
    ];

    foreach ($urls as $url) {
        $this->getJson($url)
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    $this->patchJson(route('hospital.estoque.bolsa.update', $bolsa->isbt), [
        'disponibilidade_bolsa' => 'disponivel',
    ])->assertUnauthorized();
});

test('funcionário de hemocentro recebe 403 nos endpoints do estoque', function () {
    $hospital = Hospital::factory()->create();
    $bolsa = BolsaSangue::factory()->noHospital($hospital)->create();
    $funcionario = Funcionario::factory()->create([
        'cnes_hem' => Hemocentro::factory(),
        'cnpj_hosp' => null,
    ]);

    $urls = [
        route('hospital.estoque.resumo'),
        route('hospital.estoque.consumo'),
        route('hospital.estoque.bolsas'),
        route('hospital.estoque.bolsa', $bolsa->isbt),
    ];

    foreach ($urls as $url) {
        $this->actingAs($funcionario, 'funcionario')
            ->getJson($url)
            ->assertForbidden();
    }

    $this->actingAs($funcionario, 'funcionario')
        ->patchJson(route('hospital.estoque.bolsa.update', $bolsa->isbt), [
            'disponibilidade_bolsa' => 'disponivel',
        ])
        ->assertForbidden();
});

test('funcionário vinculado ao hospital acessa os dados da própria instituição', function () {
    $hospital = Hospital::factory()->create(['nome_hosp' => 'Hospital do Funcionário']);
    $funcionario = Funcionario::factory()->create([
        'cnpj_hosp' => $hospital->cnpj_hosp,
        'cnes_hem' => null,
    ]);

    $this->actingAs($funcionario, 'funcionario')
        ->getJson(route('hospital.estoque.resumo'))
        ->assertOk()
        ->assertJsonPath('hospital.nome', 'Hospital do Funcionário');
});

test('os endpoints do estoque são limitados a vinte requisições por minuto', function () {
    $hospital = Hospital::factory()->create();

    foreach (range(1, 20) as $requisicao) {
        $this->actingAs($hospital, 'hospital')
            ->getJson(route('hospital.estoque.resumo'))
            ->assertOk();
    }

    $this->actingAs($hospital, 'hospital')
        ->getJson(route('hospital.estoque.resumo'))
        ->assertStatus(429);
});
