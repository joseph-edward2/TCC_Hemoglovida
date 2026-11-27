<?php

use App\Models\Funcionario;
use App\Models\Hemocentro;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);

test('mostra apenas os agendamentos do hemocentro autenticado no dia local', function () {
    $this->travelTo(Carbon::parse('2026-11-24 01:30:00', 'UTC'));
    $hemocentro = Hemocentro::factory()->create();
    $outroHemocentro = Hemocentro::factory()->create();
    $nome = 'Doador <Teste>';

    createAtendimentoAppointment($hemocentro, 1, ['data_hora_agendamento' => '2026-11-23 03:00:00'], $nome);
    createAtendimentoAppointment($hemocentro, 2, ['data_hora_coleta' => '2026-11-24 01:15:00'], 'Doador com coleta anterior');
    createAtendimentoAppointment($hemocentro, 3, ['data_hora_agendamento' => '2026-11-23 02:59:59'], 'Doador de ontem');
    createAtendimentoAppointment($hemocentro, 4, ['data_hora_agendamento' => '2026-11-24 03:00:00'], 'Doador de amanhã');
    createAtendimentoAppointment($hemocentro, 5, ['status_agendamento' => 'cancelado'], 'Doador cancelado');
    createAtendimentoAppointment($outroHemocentro, 6, [], 'Doador de outro hemocentro');

    $this->actingAs($hemocentro, 'hemocentro')
        ->get(route('hemocentro.atendimento'))
        ->assertOk()
        ->assertSeeInOrder(['Agendamentos de Hoje', $nome, '00:00', 'Doador com coleta anterior', '22:00'])
        ->assertSee('data-status="concluido"', false)
        ->assertSeeText('Doador cancelado')
        ->assertSee('data-cpf="123.***.***-01"', false)
        ->assertDontSee($nome, false)
        ->assertDontSee(['12300000001', 'Doador de ontem', 'Doador de amanhã', 'Doador de outro hemocentro']);
});

test('mostra a agenda vazia quando o hemocentro não tem agendamentos no dia', function () {
    $hemocentro = Hemocentro::factory()->create();

    $this->actingAs($hemocentro, 'hemocentro')
        ->get(route('hemocentro.atendimento'))
        ->assertOk()
        ->assertSeeText('Nenhum agendamento para hoje.');
});

test('registra uma bolsa de sangue ao concluir o atendimento', function () {
    $this->travelTo(Carbon::parse('2026-11-24 01:30:00', 'UTC'));
    $funcionario = Funcionario::factory()->create();
    $hemocentro = Hemocentro::findOrFail($funcionario->cnes_hem);
    createAtendimentoAppointment($hemocentro, 1);

    $this->actingAs($funcionario, 'funcionario')
        ->postJson(route('hemocentro.atendimento.chegada', 1))
        ->assertOk()
        ->assertExactJson(['status' => 'chegou']);

    $this->assertDatabaseHas('agendamento', ['protocolo_agendamento' => 1, 'status_agendamento' => 'chegou']);
    $this->assertDatabaseEmpty('bolsa_sangue');

    $this->postJson(route('hemocentro.atendimento.concluir', 1))
        ->assertOk()
        ->assertExactJson(['status' => 'concluido']);

    $this->travel(1)->minutes();
    $this->postJson(route('hemocentro.atendimento.concluir', 1))
        ->assertOk()
        ->assertExactJson(['status' => 'concluido']);

    $collection = [
        'protocolo_agendamento' => 1,
        'data_hora_coleta' => '2026-11-24 01:30:00',
        'status_bolsa_doacao' => 'coletada',
        'volume_ml_doacao' => 450,
    ];

    $this->assertDatabaseHas('agendamento', $collection + ['status_agendamento' => 'concluido']);
    $this->assertDatabaseHas('bolsa_sangue', $collection + [
        'cnes_hem' => $hemocentro->cnes_hem,
        'quant_bolsa' => 1,
        'tipo_bolsa' => 'O+',
        'disponibilidade_bolsa' => 'em_analise',
        'data_venc_bolsa' => null,
    ]);
    $this->assertDatabaseCount('bolsa_sangue', 1);
    $this->get(route('hemocentro.atendimento'))
        ->assertOk()
        ->assertSee('data-status="concluido"', false);
});

test('mantém o volume e o status de bolsa já informados ao concluir', function () {
    $this->travelTo(Carbon::parse('2026-11-24 01:30:00', 'UTC'));
    $hemocentro = Hemocentro::factory()->create();
    $dadosColeta = ['status_bolsa_doacao' => 'em_analise', 'volume_ml_doacao' => 420];
    createAtendimentoAppointment($hemocentro, 1, $dadosColeta + ['status_agendamento' => 'chegou']);

    $this->actingAs($hemocentro, 'hemocentro')
        ->postJson(route('hemocentro.atendimento.concluir', 1))
        ->assertOk();

    $this->assertDatabaseHas('bolsa_sangue', ['protocolo_agendamento' => 1] + $dadosColeta);
});

test('retorna 409 ao concluir antes de confirmar a chegada', function () {
    $this->travelTo(Carbon::parse('2026-11-24 01:30:00', 'UTC'));
    $hemocentro = Hemocentro::factory()->create();
    createAtendimentoAppointment($hemocentro, 1);

    $this->actingAs($hemocentro, 'hemocentro')
        ->postJson(route('hemocentro.atendimento.concluir', 1))
        ->assertConflict()
        ->assertJsonPath('message', 'Confirme a chegada antes de registrar a doação.');

    $this->assertDatabaseHas('agendamento', ['protocolo_agendamento' => 1, 'status_agendamento' => 'agendado', 'data_hora_coleta' => null]);
    $this->assertDatabaseEmpty('bolsa_sangue');
});

test('mantém o agendamento cancelado por ausência no atendimento do dia', function (string $status) {
    $this->travelTo(Carbon::parse('2026-11-24 01:30:00', 'UTC'));
    $funcionario = Funcionario::factory()->create();
    $hemocentro = Hemocentro::findOrFail($funcionario->cnes_hem);
    createAtendimentoAppointment($hemocentro, 1, ['status_agendamento' => $status]);
    $url = route('hemocentro.atendimento.ausencia', 1);

    $this->actingAs($funcionario, 'funcionario')->get(route('hemocentro.atendimento'))
        ->assertSeeInOrder(['Não compareceu', 'Manter agendamento', 'Cancelar agendamento']);
    $this->postJson($url)->assertOk()->assertExactJson(['status' => 'cancelado']);
    $this->postJson($url)->assertOk();

    $this->assertDatabaseHas('agendamento', ['protocolo_agendamento' => 1, 'status_agendamento' => 'cancelado', 'data_hora_coleta' => null]);
    $this->assertDatabaseEmpty('bolsa_sangue');
    $this->get(route('hemocentro.atendimento'))
        ->assertSee('data-status="cancelado"', false)
        ->assertSeeText('Doador de teste');
})->with(['agendado', 'atrasado']);

test('retorna 409 ao registrar ausência após chegada ou coleta', function (string $status, ?string $coleta) {
    $this->travelTo(Carbon::parse('2026-11-24 01:30:00', 'UTC'));
    $hemocentro = Hemocentro::factory()->create();
    $dados = ['status_agendamento' => $status, 'data_hora_coleta' => $coleta];
    createAtendimentoAppointment($hemocentro, 1, $dados);

    $this->actingAs($hemocentro, 'hemocentro')->postJson(route('hemocentro.atendimento.ausencia', 1))
        ->assertConflict()->assertJsonPath('message', 'A ausência não pode ser registrada no status atual.');
    $this->assertDatabaseHas('agendamento', ['protocolo_agendamento' => 1] + $dados);
})->with([['chegou', null], ['concluido', null], ['agendado', '2026-11-24 01:10:00']]);

test('retorna 404 ao registrar ausência de outro dia', function () {
    $this->travelTo(Carbon::parse('2026-11-25 01:30:00', 'UTC'));
    $hemocentro = Hemocentro::factory()->create();
    createAtendimentoAppointment($hemocentro, 1);

    $this->actingAs($hemocentro, 'hemocentro')->postJson(route('hemocentro.atendimento.ausencia', 1))->assertNotFound();
    $this->assertDatabaseHas('agendamento', ['protocolo_agendamento' => 1, 'status_agendamento' => 'agendado']);
});

test('retorna 404 ao registrar uma etapa de outro hemocentro', function (string $acao) {
    $this->travelTo(Carbon::parse('2026-11-24 01:30:00', 'UTC'));
    $hemocentro = Hemocentro::factory()->create();
    $outroHemocentro = Hemocentro::factory()->create();
    $status = $acao === 'concluir' ? 'chegou' : 'agendado';
    createAtendimentoAppointment($outroHemocentro, 1, ['status_agendamento' => $status]);

    $this->actingAs($hemocentro, 'hemocentro')
        ->postJson(route('hemocentro.atendimento.'.$acao, 1))
        ->assertNotFound();

    $this->assertDatabaseHas('agendamento', ['protocolo_agendamento' => 1, 'status_agendamento' => $status, 'data_hora_coleta' => null]);
    $this->assertDatabaseEmpty('bolsa_sangue');
})->with(['chegada', 'concluir', 'ausencia']);

test('retorna 401 ao registrar uma etapa sem autenticação', function (string $acao) {
    $this->postJson(route('hemocentro.atendimento.'.$acao, 1))->assertUnauthorized();
})->with(['chegada', 'concluir', 'ausencia']);

/**
 * @param  array{data_hora_agendamento?: string, data_hora_coleta?: string, status_agendamento?: string, status_bolsa_doacao?: string, volume_ml_doacao?: int}  $attributes
 */
function createAtendimentoAppointment(Hemocentro $hemocentro, int $protocolo, array $attributes = [], string $nome = 'Doador de teste'): void
{
    $cpf = '123'.str_pad((string) $protocolo, 8, '0', STR_PAD_LEFT);
    DB::table('doador')->insert([
        'cpf_doador' => $cpf,
        'nome_doador' => $nome,
        'email_doador' => "doador-$protocolo@example.test",
        'senha_doador' => 'hash-de-teste',
        'fone_doador' => '11900000000',
        'tipo_sanguineo' => 'O+',
        'data_nasc_doador' => '1990-01-01',
    ]);
    $evento = DB::table('evento')->insertGetId([
        'cnes_hem' => $hemocentro->cnes_hem,
        'tipo_evento' => 'Coleta de teste',
        'data_hora_evento' => '2026-11-24 01:00:00',
        'local_evento' => 'Sala de coleta',
    ], 'id_evento');
    DB::table('agendamento')->insert($attributes + [
        'protocolo_agendamento' => $protocolo,
        'cnes_hem' => $hemocentro->cnes_hem,
        'cpf_doador' => $cpf,
        'id_evento' => $evento,
        'data_hora_agendamento' => '2026-11-24 01:00:00',
    ]);
}
