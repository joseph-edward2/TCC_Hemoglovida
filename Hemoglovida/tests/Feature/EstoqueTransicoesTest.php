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

test('em trânsito pode virar disponível e grava o recebimento', function () {
    $hospital = Hospital::factory()->create();
    $bolsa = BolsaSangue::factory()->noHospital($hospital)->emTransito()->create([
        'data_hora_envio_bolsa' => now()->subHours(2),
    ]);

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->patchJson(route('hospital.estoque.bolsa.update', $bolsa->isbt), [
            'disponibilidade_bolsa' => StatusBolsa::Disponivel->value,
        ])
        ->assertOk()
        ->assertJsonPath('data.disponibilidade_bolsa', 'disponivel');

    $bolsa->refresh();

    expect($bolsa->disponibilidade_bolsa)->toBe(StatusBolsa::Disponivel)
        ->and($bolsa->data_hora_recebimento_bolsa->toISOString())->toBe(now()->toISOString());
});

test('disponível e reservada podem alternar entre si', function () {
    $hospital = Hospital::factory()->create();
    $bolsa = BolsaSangue::factory()->noHospital($hospital)->create();

    $rota = route('hospital.estoque.bolsa.update', $bolsa->isbt);

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->patchJson($rota, [
            'disponibilidade_bolsa' => StatusBolsa::Reservada->value,
            'paciente_destino_bolsa' => 'Paciente / Centro Cirúrgico',
        ])
        ->assertOk();

    expect($bolsa->refresh()->disponibilidade_bolsa)->toBe(StatusBolsa::Reservada)
        ->and($bolsa->paciente_destino_bolsa)->toBe('Paciente / Centro Cirúrgico');

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->patchJson($rota, [
            'disponibilidade_bolsa' => StatusBolsa::Disponivel->value,
        ])
        ->assertOk();

    expect($bolsa->refresh()->disponibilidade_bolsa)->toBe(StatusBolsa::Disponivel);
});

test('saídas gravam o timestamp e exigem paciente', function (StatusBolsa $origem, StatusBolsa $destino) {
    $hospital = Hospital::factory()->create();
    $bolsa = BolsaSangue::factory()->noHospital($hospital)->create([
        'disponibilidade_bolsa' => $origem->value,
    ]);

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->patchJson(route('hospital.estoque.bolsa.update', $bolsa->isbt), [
            'disponibilidade_bolsa' => $destino->value,
            'paciente_destino_bolsa' => 'Paciente / UTI',
        ])
        ->assertOk();

    $bolsa->refresh();

    expect($bolsa->disponibilidade_bolsa)->toBe($destino)
        ->and($bolsa->data_hora_saida_bolsa?->toISOString())->toBe(now()->toISOString())
        ->and($bolsa->paciente_destino_bolsa)->toBe('Paciente / UTI');
})->with([
    'disponível para utilizada' => [StatusBolsa::Disponivel, StatusBolsa::Utilizada],
    'reservada para descartada' => [StatusBolsa::Reservada, StatusBolsa::Descartada],
]);

test('bolsa vencida só pode ser descartada', function () {
    $hospital = Hospital::factory()->create();
    $bolsa = BolsaSangue::factory()->noHospital($hospital)->vencida()->create();

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->patchJson(route('hospital.estoque.bolsa.update', $bolsa->isbt), [
            'disponibilidade_bolsa' => StatusBolsa::Disponivel->value,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['disponibilidade_bolsa' => 'Bolsa vencida só pode ser descartada.']);

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->patchJson(route('hospital.estoque.bolsa.update', $bolsa->isbt), [
            'disponibilidade_bolsa' => StatusBolsa::Descartada->value,
        ])
        ->assertOk();

    expect($bolsa->refresh()->disponibilidade_bolsa)->toBe(StatusBolsa::Descartada)
        ->and($bolsa->data_hora_saida_bolsa)->not->toBeNull();
});

test('transições inválidas retornam 422 com mensagem em português', function (StatusBolsa $origem, StatusBolsa $destino, string $mensagem) {
    $hospital = Hospital::factory()->create();
    $bolsa = BolsaSangue::factory()->noHospital($hospital)->create([
        'disponibilidade_bolsa' => $origem->value,
    ]);

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->patchJson(route('hospital.estoque.bolsa.update', $bolsa->isbt), [
            'disponibilidade_bolsa' => $destino->value,
            'paciente_destino_bolsa' => 'Paciente / UTI',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['disponibilidade_bolsa' => $mensagem]);

    expect($bolsa->refresh()->disponibilidade_bolsa)->toBe($origem);
})->with([
    'em trânsito para reservada' => [StatusBolsa::EmTransito, StatusBolsa::Reservada, 'Transição de Em trânsito para Reservada não é permitida.'],
    'reservada para em trânsito' => [StatusBolsa::Reservada, StatusBolsa::EmTransito, 'Transição de Reservada para Em trânsito não é permitida.'],
    'utilizada é final' => [StatusBolsa::Utilizada, StatusBolsa::Disponivel, 'Bolsas utilizadas ou descartadas são finais e não podem ser alteradas.'],
    'descartada é final' => [StatusBolsa::Descartada, StatusBolsa::Reservada, 'Bolsas utilizadas ou descartadas são finais e não podem ser alteradas.'],
]);

test('paciente de destino é obrigatório ao reservar ou utilizar e limitado a 150 caracteres', function (StatusBolsa $destino) {
    $hospital = Hospital::factory()->create();
    $bolsa = BolsaSangue::factory()->noHospital($hospital)->create();

    $rota = route('hospital.estoque.bolsa.update', $bolsa->isbt);

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->patchJson($rota, ['disponibilidade_bolsa' => $destino->value])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['paciente_destino_bolsa' => 'Informe o paciente de destino.']);

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->patchJson($rota, [
            'disponibilidade_bolsa' => $destino->value,
            'paciente_destino_bolsa' => str_repeat('a', 151),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['paciente_destino_bolsa' => 'O paciente de destino deve ter no máximo 150 caracteres.']);
})->with([
    'reservar' => [StatusBolsa::Reservada],
    'utilizar' => [StatusBolsa::Utilizada],
]);

test('status inválido retorna 422', function () {
    $hospital = Hospital::factory()->create();
    $bolsa = BolsaSangue::factory()->noHospital($hospital)->create();

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->patchJson(route('hospital.estoque.bolsa.update', $bolsa->isbt), [
            'disponibilidade_bolsa' => 'inexistente',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['disponibilidade_bolsa' => 'Status da bolsa inválido.']);
});

test('não é possível editar uma bolsa de outro hospital', function () {
    $hospital = Hospital::factory()->create();
    $outroHospital = Hospital::factory()->create();
    $bolsa = BolsaSangue::factory()->noHospital($outroHospital)->create();

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->patchJson(route('hospital.estoque.bolsa.update', $bolsa->isbt), [
            'disponibilidade_bolsa' => StatusBolsa::Utilizada->value,
            'paciente_destino_bolsa' => 'Paciente / UTI',
        ])
        ->assertNotFound();
});
