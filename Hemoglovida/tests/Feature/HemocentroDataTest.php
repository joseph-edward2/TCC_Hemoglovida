<?php

use App\Models\Agendamento;
use App\Models\BolsaSangue;
use App\Models\Evento;
use App\Models\Funcionario;
use App\Models\Hemocentro;
use App\Models\Pedido;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('hashes the hemocenter password and hides it from serialization', function () {
    $hemocentro = Hemocentro::factory()->create(['senha_hem' => 'hemocentro123']);

    expect(Hash::check('hemocentro123', $hemocentro->senha_hem))->toBeTrue()
        ->and($hemocentro->getAuthPasswordName())->toBe('senha_hem')
        ->and($hemocentro->toArray())->not->toHaveKey('senha_hem');
});

it('rejects a second hemocenter with the same cnpj', function () {
    $cnpj = '22333444000181';
    Hemocentro::factory()->create(['cnpj_hem' => $cnpj]);

    expect(fn () => Hemocentro::factory()->create(['cnpj_hem' => $cnpj]))->toThrow(QueryException::class);
});

it('links employees, orders and blood bags to the hemocenter', function () {
    $hemocentro = Hemocentro::factory()->create();

    $funcionario = Funcionario::factory()->create(['cnes_hem' => $hemocentro->cnes_hem]);
    Pedido::factory()->create(['cnes_hem' => $hemocentro->cnes_hem]);
    BolsaSangue::factory()->create(['cnes_hem' => $hemocentro->cnes_hem]);

    expect($funcionario->hemocentro)->toBeInstanceOf(Hemocentro::class)
        ->and(Pedido::where('cnes_hem', $hemocentro->cnes_hem)->first())->toBeInstanceOf(Pedido::class)
        ->and(BolsaSangue::where('cnes_hem', $hemocentro->cnes_hem)->first())->toBeInstanceOf(BolsaSangue::class);
});

it('keeps the event and appointment relationships', function () {
    $hemocentro = Hemocentro::factory()->create();

    $evento = Evento::factory()->create(['cnes_hem' => $hemocentro->cnes_hem]);
    $agendamento = Agendamento::factory()->create([
        'cnes_hem' => $hemocentro->cnes_hem,
        'id_evento' => $evento->id_evento,
    ]);

    expect(Evento::where('cnes_hem', $hemocentro->cnes_hem)->first())->toBeInstanceOf(Evento::class)
        ->and(Agendamento::where('cnes_hem', $hemocentro->cnes_hem)->first())->toBeInstanceOf(Agendamento::class)
        ->and($agendamento->hemocentro)->toBeInstanceOf(Hemocentro::class)
        ->and($agendamento->evento)->toBeInstanceOf(Evento::class);
});
