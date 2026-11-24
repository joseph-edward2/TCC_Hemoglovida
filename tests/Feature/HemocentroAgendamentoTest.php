<?php

use App\Models\Funcionario;
use App\Models\Hemocentro;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);

test('conta reservas por minuto no mês local apenas para o hemocentro autenticado', function () {
    $this->travelTo(Carbon::parse('2026-12-01 02:00:00', 'UTC'));
    $center = Hemocentro::factory()->create();
    $other = Hemocentro::factory()->create();
    $cpf = '12345678900';
    DB::table('doador')->insert([
        'cpf_doador' => $cpf, 'nome_doador' => 'Doador', 'email_doador' => 'doador@example.test',
        'senha_doador' => 'teste', 'fone_doador' => '11900000000', 'tipo_sanguineo' => 'O+',
        'data_nasc_doador' => '1990-01-01',
    ]);
    foreach ([$center, $other] as $hemocentro) {
        $event = DB::table('evento')->insertGetId([
            'cnes_hem' => $hemocentro->cnes_hem, 'tipo_evento' => 'Coleta',
            'data_hora_evento' => '2026-11-30 12:00:00', 'local_evento' => 'Sala de coleta',
        ], 'id_evento');
        $booking = ['cpf_doador' => $cpf, 'id_evento' => $event, 'cnes_hem' => $hemocentro->cnes_hem];
        if ($hemocentro->is($other)) {
            DB::table('agendamento')->insert($booking + ['data_hora_agendamento' => '2026-11-30 12:00:00']);
        } else {
            foreach (['2026-12-01 02:00:00', '2026-12-01 02:00:30', '2026-12-01 03:00:00'] as $date) {
                DB::table('agendamento')->insert($booking + ['data_hora_agendamento' => $date]);
            }
            DB::table('agendamento')->insert($booking + ['data_hora_agendamento' => '2026-11-30 10:00:00', 'status_agendamento' => 'cancelado']);
        }
    }

    $this->actingAs($center, 'hemocentro')->get(route('hemocentro.agendamento'))
        ->assertOk()->assertViewHas('agenda', fn (array $agenda): bool => $agenda['date'] === '2026-11-30'
            && $agenda['bookings'] === ['2026-11-30' => ['23:00' => 2]]);
    $this->getJson(route('hemocentro.agendamento', ['mes' => '2026-12']))
        ->assertOk()->assertExactJson(['bookings' => ['2026-12-01' => ['00:00' => 1]]]);
});

test('salva e recarrega horários, exceções e bloqueios apenas no hemocentro do funcionário', function () {
    $center = Hemocentro::factory()->create();
    $other = Hemocentro::factory()->create();
    $employee = Funcionario::factory()->create(['cnes_hem' => $center->cnes_hem]);
    $settings = agendaSettings();

    $this->actingAs($employee, 'funcionario')->putJson(route('hemocentro.agendamento.update'), [
        'revision' => 0, 'cnes_hem' => $other->cnes_hem,
    ] + $settings)->assertOk()->assertJsonPath('revision', 1);
    $this->get(route('hemocentro.agendamento'))
        ->assertOk()->assertViewHas('agenda', fn (array $agenda): bool => $agenda['settings'] == $settings && $agenda['revision'] === 1);
    $this->assertDatabaseHas('hemocentro', ['cnes_hem' => $other->cnes_hem, 'configuracao_agenda' => null]);
    $this->assertDatabaseEmpty('agendamento');

    $this->putJson(route('hemocentro.agendamento.update'), ['revision' => 0, 'blocked' => []] + $settings)
        ->assertConflict();
    $this->assertDatabaseHas('hemocentro', ['cnes_hem' => $center->cnes_hem, 'versao_agenda' => 1]);
});

test('rejeita datas inválidas, expediente invertido e horários sobrepostos', function () {
    $center = Hemocentro::factory()->create();
    $settings = agendaSettings();
    $settings['blocked'] = ['2026-11-31'];
    $settings['pattern'][0]['end'] = '07:30';
    $settings['overrides'][0]['horarios'][] = $settings['overrides'][0]['horarios'][0];

    $this->actingAs($center, 'hemocentro')->putJson(route('hemocentro.agendamento.update'), ['revision' => 0] + $settings)
        ->assertUnprocessable()->assertJsonValidationErrors('blocked.0');
    $settings['blocked'] = [];
    $this->putJson(route('hemocentro.agendamento.update'), ['revision' => 0] + $settings)
        ->assertUnprocessable()->assertJsonValidationErrors(['pattern.0.end', 'overrides.0.horarios']);
    $this->assertDatabaseHas('hemocentro', ['cnes_hem' => $center->cnes_hem, 'configuracao_agenda' => null]);
});

test('exige autenticação para consultar ou salvar a agenda', function () {
    $this->getJson(route('hemocentro.agendamento'))->assertUnauthorized();
    $this->putJson(route('hemocentro.agendamento.update'))->assertUnauthorized();
});

/** @return array{pattern: list<array>, overrides: list<array>, blocked: list<string>} */
function agendaSettings(): array
{
    return [
        'pattern' => array_fill(0, 7, ['open' => true, 'start' => '08:00', 'end' => '12:30', 'cap' => 20]),
        'overrides' => [['date' => '2026-11-30', 'open' => true, 'horarios' => [
            ['start' => '09:00', 'end' => '10:00', 'vagas' => 4, 'active' => true],
        ]]],
        'blocked' => ['2026-12-01'],
    ];
}
