<?php

use App\Models\Agendamento;
use App\Models\Doador;
use App\Models\Evento;
use App\Models\Hemocentro;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function agendamentoPayload(Evento $evento, string $dataHora, string $cpf = '12345678901'): array
{
    return [
        'cpf_doador' => $cpf,
        'id_evento' => $evento->id_evento,
        'cnes_hem' => $evento->hemocentro->cnes_hem,
        'data_hora_agendamento' => $dataHora,
    ];
}

it('creates an appointment with a future available time', function () {
    Doador::factory()->create(['cpf_doador' => '12345678901']);
    $hemocentro = Hemocentro::factory()->create();
    $evento = Evento::factory()->for($hemocentro)->create();
    $dataHora = now()->addDay()->setTime(9, 0)->format('Y-m-d H:i:s');

    $response = $this->postJson('/api/v1/agendamentos', agendamentoPayload($evento, $dataHora));

    $response->assertCreated()
        ->assertJsonPath('data.cpf_doador', '12345678901')
        ->assertJsonPath('data.id_evento', $evento->id_evento)
        ->assertJsonPath('data.cnes_hem', $hemocentro->cnes_hem)
        ->assertJsonPath('data.status_agendamento', 'agendado');
    $this->assertDatabaseHas('agendamento', [
        'cpf_doador' => '12345678901',
        'id_evento' => $evento->id_evento,
        'cnes_hem' => $hemocentro->cnes_hem,
        'status_agendamento' => 'agendado',
    ]);
});

it('returns 422 when an appointment is scheduled in the past', function () {
    Doador::factory()->create(['cpf_doador' => '12345678901']);
    $evento = Evento::factory()->create();

    $response = $this->postJson('/api/v1/agendamentos', agendamentoPayload(
        $evento,
        now()->subMinute()->format('Y-m-d H:i:s'),
    ));

    $response->assertUnprocessable()
        ->assertInvalid(['data_hora_agendamento']);
});

it('returns 422 when the time is already booked at the hemocenter', function () {
    Doador::factory()->create(['cpf_doador' => '12345678901']);
    $hemocentro = Hemocentro::factory()->create();
    $evento = Evento::factory()->for($hemocentro)->create();
    $dataHora = now()->addDay()->setTime(10, 0)->format('Y-m-d H:i:s');
    Agendamento::factory()->create([
        'cnes_hem' => $hemocentro->cnes_hem,
        'id_evento' => $evento->id_evento,
        'data_hora_agendamento' => $dataHora,
    ]);

    $response = $this->postJson('/api/v1/agendamentos', agendamentoPayload($evento, $dataHora));

    $response->assertUnprocessable()
        ->assertInvalid(['data_hora_agendamento']);
});

it('lists only the appointments belonging to the supplied donor CPF', function () {
    Doador::factory()->create(['cpf_doador' => '12345678901']);
    Doador::factory()->create(['cpf_doador' => '99999999999']);
    $firstAppointment = Agendamento::factory()->create(['cpf_doador' => '12345678901']);
    Agendamento::factory()->create(['cpf_doador' => '99999999999']);

    $response = $this->getJson('/api/v1/agendamentos?cpf_doador=12345678901');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.protocolo_agendamento', $firstAppointment->protocolo_agendamento);
});

it('updates an appointment to another available future time', function () {
    Doador::factory()->create(['cpf_doador' => '12345678901']);
    $hemocentro = Hemocentro::factory()->create();
    $evento = Evento::factory()->for($hemocentro)->create();
    $agendamento = Agendamento::factory()->create([
        'cpf_doador' => '12345678901',
        'cnes_hem' => $hemocentro->cnes_hem,
        'id_evento' => $evento->id_evento,
        'data_hora_agendamento' => now()->addDay()->setTime(9, 0),
    ]);
    $novaDataHora = now()->addDays(2)->setTime(14, 0)->format('Y-m-d H:i:s');

    $response = $this->putJson("/api/v1/agendamentos/{$agendamento->protocolo_agendamento}", [
        'cpf_doador' => '12345678901',
        'data_hora_agendamento' => $novaDataHora,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.protocolo_agendamento', $agendamento->protocolo_agendamento);
    $this->assertDatabaseHas('agendamento', [
        'protocolo_agendamento' => $agendamento->protocolo_agendamento,
        'data_hora_agendamento' => $novaDataHora,
    ]);
});

it('returns 404 when a different donor tries to view an appointment', function () {
    Doador::factory()->create(['cpf_doador' => '12345678901']);
    $agendamento = Agendamento::factory()->create(['cpf_doador' => '12345678901']);

    $this->getJson("/api/v1/agendamentos/{$agendamento->protocolo_agendamento}?cpf_doador=99999999999")
        ->assertNotFound();
});

it('cancels an appointment and releases its time', function () {
    Doador::factory()->create(['cpf_doador' => '12345678901']);
    Doador::factory()->create(['cpf_doador' => '99999999999']);
    $hemocentro = Hemocentro::factory()->create();
    $evento = Evento::factory()->for($hemocentro)->create();
    $dataHora = now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s');
    $agendamento = Agendamento::factory()->create([
        'cpf_doador' => '12345678901',
        'cnes_hem' => $hemocentro->cnes_hem,
        'id_evento' => $evento->id_evento,
        'data_hora_agendamento' => $dataHora,
    ]);

    $response = $this->deleteJson("/api/v1/agendamentos/{$agendamento->protocolo_agendamento}?cpf_doador=12345678901");

    $response->assertOk()
        ->assertJsonPath('data.status_agendamento', 'cancelado');
    $this->assertDatabaseHas('agendamento', [
        'protocolo_agendamento' => $agendamento->protocolo_agendamento,
        'status_agendamento' => 'cancelado',
    ]);

    $this->postJson('/api/v1/agendamentos', agendamentoPayload($evento, $dataHora, '99999999999'))
        ->assertCreated();
});
