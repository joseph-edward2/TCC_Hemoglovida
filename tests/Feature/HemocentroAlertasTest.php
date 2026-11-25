<?php

use App\Models\Funcionario;
use App\Models\Hemocentro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('mantém a capa da campanha até a substituição por outra imagem válida', function () {
    $center = Hemocentro::factory()->create();
    $this->actingAs($center, 'hemocentro');
    $form = dadosFormularioEvento();
    $original = UploadedFile::fake()->image('capa.png');
    $this->post(route('hemocentro.eventos.store', 'campanha'), $form + ['imagem' => $original])->assertSessionHasNoErrors();
    $id = DB::table('evento')->sole()->id_evento;
    $edit = route('hemocentro.eventos.update', ['campanha', $id]);
    $image = route('hemocentro.eventos.imagem', $id);
    $this->get(route('hemocentro.alertas'))->assertSee($image);
    $this->put($edit, $form)->assertSessionHasNoErrors();
    $this->put($edit, $form + ['imagem' => UploadedFile::fake()->create('texto.txt')])->assertSessionHasErrors('imagem');
    $this->get($image)->assertOk()->assertHeader('Content-Type', 'image/png')->assertContent($original->getContent());

    $replacement = UploadedFile::fake()->image('nova.jpg');
    $this->put($edit, $form + ['imagem' => $replacement])->assertSessionHasNoErrors();
    $this->get($image)->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertContent($replacement->getContent());
    $this->actingAs(Hemocentro::factory()->create(), 'hemocentro')->get($image)->assertNotFound();
});

/** @return array{titulo: string, local: string, mensagem: string, tipos: list<string>, urgencia: string, publico: string, inicio: string, expiracao: string, status: string} */
function dadosFormularioEvento(): array
{
    return [
        'titulo' => 'Coleta de férias', 'local' => 'Sala 2', 'mensagem' => 'Doação <especial>',
        'tipos' => ['O-', 'A+'], 'urgencia' => 'Crítica', 'publico' => 'Novos doadores',
        'inicio' => '2026-11-24', 'expiracao' => '2026-11-30', 'status' => 'rascunho',
    ];
}

/** @param array<string, mixed> $dados */
function registrarEventoPainel(Hemocentro $centro, string $tipo, array $dados = []): int
{
    return DB::table('evento')->insertGetId($dados + [
        'cnes_hem' => $centro->cnes_hem, 'tipo_evento' => $tipo,
        'data_hora_evento' => '2026-11-24 03:00:00', 'local_evento' => 'Sala de coleta',
    ], 'id_evento');
}

test('protege a página e as ações de eventos de visitantes', function () {
    $this->get(route('hemocentro.alertas'))->assertRedirectToRoute('hemocentro.login');
    $this->post(route('hemocentro.eventos.store', 'alerta'))->assertRedirectToRoute('hemocentro.login');
    $this->put(route('hemocentro.eventos.update', ['alerta', 1]))->assertRedirectToRoute('hemocentro.login');
    $this->patch(route('hemocentro.eventos.status', ['alerta', 1]))->assertRedirectToRoute('hemocentro.login');
    $this->delete(route('hemocentro.eventos.destroy', ['alerta', 1]))->assertRedirectToRoute('hemocentro.login');
});

test('mostra os formulários e os indicadores vazios de um novo hemocentro', function () {
    $center = Hemocentro::factory()->create();
    $this->actingAs($center, 'hemocentro')->get(route('hemocentro.alertas'))
        ->assertOk()->assertSeeText('Nenhum alerta cadastrado.')->assertSeeText('Nenhuma campanha cadastrada.')
        ->assertSee(route('hemocentro.eventos.store', 'alerta'))->assertSee(route('hemocentro.eventos.store', 'campanha'))
        ->assertSee($center->endereco_hem)->assertViewHas('activeAlerts', 0)->assertViewHas('reached', 0)
        ->assertViewHas('responseRate', fn ($rate): bool => $rate === null);
});

test('salva edita e publica eventos do hemocentro pelo formulário', function (string $categoria, string $guard) {
    $center = Hemocentro::factory()->create();
    $actor = $guard === 'hemocentro' ? $center : Funcionario::factory()->create(['cnes_hem' => $center->cnes_hem]);
    $this->actingAs($actor, $guard);
    $data = dadosFormularioEvento();
    $this->post(route('hemocentro.eventos.store', $categoria), $data + ['cnes_hem' => '9999999'])
        ->assertRedirectToRoute('hemocentro.alertas')->assertSessionHasNoErrors();
    $row = DB::table('evento')->sole();
    expect($row->cnes_hem)->toBe($center->cnes_hem)
        ->and($row->tipo_evento)->toBe(ucfirst($categoria))
        ->and($row->status_evento)->toBe('rascunho')
        ->and($row->data_hora_evento)->toBe('2026-11-24 03:00:00')
        ->and(json_decode($row->tipos_sanguineos, true))->toBe(['O-', 'A+']);
    $this->get(route('hemocentro.inicio'))->assertDontSeeText($data['titulo']);
    $this->get(route('hemocentro.alertas', ['editar' => $row->id_evento]))
        ->assertOk()->assertSee(route('hemocentro.eventos.update', [$categoria, $row->id_evento]))
        ->assertSee($data['titulo'])->assertSee($data['mensagem'])->assertDontSee($data['mensagem'], false)
        ->assertSee('value="O-" checked', false)->assertSee('value="A+" checked', false)
        ->assertSee('2026-11-30')->assertSeeText('Rascunho');

    $data['titulo'] = 'Coleta de verão';
    $data['status'] = 'publicado';
    unset($data['tipos'], $data['expiracao']);
    $this->put(route('hemocentro.eventos.update', [$categoria, $row->id_evento]), $data)
        ->assertRedirectToRoute('hemocentro.alertas')->assertSessionHasNoErrors();
    $this->assertDatabaseHas('evento', [
        'id_evento' => $row->id_evento, 'titulo_evento' => 'Coleta de verão', 'status_evento' => 'publicado',
        'local_evento' => 'Sala 2', 'desc_evento' => $data['mensagem'], 'urgencia' => 'Crítica',
        'publico_alvo' => 'Novos doadores', 'tipos_sanguineos' => '[]', 'expiracao_evento' => null,
    ]);
    $this->get(route('hemocentro.inicio'))->assertSeeText($data['titulo']);
})->with([['alerta', 'hemocentro'], ['campanha', 'funcionario']]);

test('lista notificações expiradas com o link de edição de cada categoria', function () {
    $this->travelTo(Carbon::parse('2026-11-25 03:00:00', 'UTC'));
    $center = Hemocentro::factory()->create();
    $alert = registrarEventoPainel($center, 'Alerta de ontem', ['expiracao_evento' => '2026-11-24']);
    $campaign = registrarEventoPainel($center, 'Campanha anterior', ['expiracao_evento' => '2026-11-23']);
    registrarEventoPainel($center, 'Alerta de hoje', ['expiracao_evento' => '2026-11-25']);
    registrarEventoPainel($center, 'Campanha permanente');
    registrarEventoPainel($center, 'Coleta', ['expiracao_evento' => '2026-11-23']);
    registrarEventoPainel(Hemocentro::factory()->create(), 'Campanha externa', ['expiracao_evento' => '2026-11-23']);

    $this->actingAs($center, 'hemocentro')->get(route('hemocentro.alertas'))
        ->assertOk()->assertViewHas('expiredEvents', fn ($events): bool => $events->pluck('id_evento')->all() === [$alert, $campaign])
        ->assertSeeInOrder(['Notificações expiradas', 'Alerta de ontem', route('hemocentro.alertas', ['editar' => $alert]).'#form-alerta',
            'Campanha anterior', route('hemocentro.alertas', ['editar' => $campaign]).'#form-campanha']);

    $this->put(route('hemocentro.eventos.update', ['alerta', $alert]), dadosFormularioEvento())->assertSessionHasNoErrors();
    $this->get(route('hemocentro.alertas'))->assertViewHas('expiredEvents', fn ($events): bool => $events->pluck('id_evento')->all() === [$campaign]);
});

test('mostra campanhas ativas do hemocentro no segundo indicador', function () {
    $this->travelTo(Carbon::parse('2026-11-24 15:00:00', 'UTC'));
    $center = Hemocentro::factory()->create();
    registrarEventoPainel($center, 'Campanha de novembro');
    registrarEventoPainel($center, 'Campanha de coleta', ['expiracao_evento' => '2026-11-24']);
    registrarEventoPainel($center, 'Campanha em revisão', ['status_evento' => 'rascunho']);
    registrarEventoPainel($center, 'Campanha de dezembro', ['data_hora_evento' => '2026-12-01 03:00:00']);
    registrarEventoPainel(Hemocentro::factory()->create(), 'Campanha externa');

    $this->actingAs($center, 'hemocentro')->get(route('hemocentro.alertas'))
        ->assertOk()->assertSeeTextInOrder(['Alertas Ativos', '0', 'Campanhas Ativas', '2', 'Doadores Alcançados']);
});

test('conta alertas ativos pelas datas locais e permite pausar republicar e excluir', function () {
    $this->travelTo(Carbon::parse('2026-11-25 02:59:59', 'UTC'));
    $center = Hemocentro::factory()->create();
    $id = registrarEventoPainel($center, 'Alerta de estoque', ['expiracao_evento' => '2026-11-24']);
    registrarEventoPainel($center, 'Alerta programado', ['data_hora_evento' => '2026-11-25 03:00:00']);
    registrarEventoPainel($center, 'Alerta vencido', ['data_hora_evento' => '2026-11-22 03:00:00', 'expiracao_evento' => '2026-11-23']);
    registrarEventoPainel($center, 'Orientação');
    $status = route('hemocentro.eventos.status', ['alerta', $id]);
    $this->actingAs($center, 'hemocentro')->get(route('hemocentro.alertas'))
        ->assertOk()->assertSeeText('Alerta de estoque')->assertSeeText('Programado')->assertSeeText('Encerrado')
        ->assertDontSeeText('Orientação')->assertViewHas('activeAlerts', 1);
    $this->patch($status, ['status' => 'pausado'])->assertRedirectToRoute('hemocentro.alertas');
    $this->get(route('hemocentro.alertas'))->assertSeeText('Pausado')->assertViewHas('activeAlerts', 0);
    $this->get(route('hemocentro.inicio'))->assertDontSeeText('Alerta de estoque');
    $this->patch($status, ['status' => 'publicado'])->assertRedirectToRoute('hemocentro.alertas');
    $this->get(route('hemocentro.alertas'))->assertViewHas('activeAlerts', 1);
    $this->delete(route('hemocentro.eventos.destroy', ['alerta', $id]))->assertRedirectToRoute('hemocentro.alertas');
    $this->assertDatabaseMissing('evento', ['id_evento' => $id]);
});

test('recusa edição e ações sobre eventos de outro hemocentro ou de outra categoria', function () {
    $center = Hemocentro::factory()->create();
    $other = Hemocentro::factory()->create();
    $foreign = registrarEventoPainel($other, 'Campanha externa');
    $collection = registrarEventoPainel($center, 'Coleta');
    $this->actingAs(Funcionario::factory()->create(['cnes_hem' => $center->cnes_hem]), 'funcionario');
    $this->get(route('hemocentro.alertas'))->assertDontSeeText('Campanha externa');
    foreach ([$foreign, $collection] as $id) {
        $this->get(route('hemocentro.alertas', ['editar' => $id]))->assertNotFound();
        $this->put(route('hemocentro.eventos.update', ['campanha', $id]), dadosFormularioEvento())->assertNotFound();
        $this->patch(route('hemocentro.eventos.status', ['campanha', $id]), ['status' => 'pausado'])->assertNotFound();
        $this->delete(route('hemocentro.eventos.destroy', ['campanha', $id]))->assertNotFound();
        $this->assertDatabaseHas('evento', ['id_evento' => $id, 'status_evento' => 'publicado', 'titulo_evento' => null]);
    }
});

test('valida os campos de publicação antes de gravar', function () {
    $this->actingAs(Hemocentro::factory()->create(), 'hemocentro');
    $data = [
        'titulo' => '', 'local' => '', 'inicio' => '2026-11-24', 'expiracao' => '2026-11-23', 'tipos' => ['X'],
        'urgencia' => 'urgente', 'publico' => 'Doadores O-', 'status' => 'invalido',
    ];
    $this->postJson(route('hemocentro.eventos.store', 'campanha'), $data)->assertUnprocessable()
        ->assertJsonValidationErrors(['titulo', 'local', 'expiracao', 'tipos.0', 'urgencia', 'publico', 'status'])
        ->assertJsonPath('errors.expiracao.0', 'A expiração deve ser igual ou posterior ao início.');
    $this->assertDatabaseEmpty('evento');
});

test('calcula alcance e respostas por evento e protege os vínculos na exclusão', function () {
    $center = Hemocentro::factory()->create();
    $other = Hemocentro::factory()->create();
    $alert = registrarEventoPainel($center, 'Alerta');
    $campaign = registrarEventoPainel($center, 'Campanha antiga', ['data_hora_evento' => '2026-11-25 01:45:32']);
    $external = registrarEventoPainel($other, 'Alerta externo');

    foreach (['11111111111', '22222222222', '33333333333'] as $cpf) {
        DB::table('doador')->insert([
            'cpf_doador' => $cpf, 'nome_doador' => 'Doador', 'email_doador' => $cpf.'@example.test',
            'senha_doador' => 'hash', 'fone_doador' => '11900000000', 'tipo_sanguineo' => 'O-', 'data_nasc_doador' => '1990-01-01',
        ]);
    }
    foreach ([[$alert, '11111111111'], [$alert, '11111111111'], [$alert, '22222222222'], [$campaign, '11111111111'], [$external, '33333333333']] as [$id, $cpf]) {
        DB::table('notificacao')->insert(['cpf_doador' => $cpf, 'id_evento' => $id, 'tipo_notificacao' => 'Evento']);
    }
    DB::table('agendamento')->insert([
        ['cpf_doador' => '11111111111', 'id_evento' => $alert, 'cnes_hem' => $center->cnes_hem, 'status_agendamento' => 'agendado', 'data_hora_agendamento' => '2026-11-24 10:00:00'],
        ['cpf_doador' => '22222222222', 'id_evento' => $alert, 'cnes_hem' => $center->cnes_hem, 'status_agendamento' => 'cancelado', 'data_hora_agendamento' => '2026-11-24 11:00:00'],
    ]);
    $this->actingAs($center, 'hemocentro')->get(route('hemocentro.alertas'))
        ->assertOk()->assertViewHas('reached', 2)->assertSeeText('25,0%')->assertSeeText('Campanha antiga')
        ->assertSeeText('Doadores alcançados: 1')->assertDontSeeText('Alerta externo');

    $this->delete(route('hemocentro.eventos.destroy', ['campanha', $campaign]))->assertSessionHasErrors('evento');
    DB::table('notificacao')->where('id_evento', $alert)->delete();
    $this->delete(route('hemocentro.eventos.destroy', ['alerta', $alert]))->assertSessionHasErrors('evento');
    $this->assertDatabaseHas('evento', ['id_evento' => $alert]);

    $this->put(route('hemocentro.eventos.update', ['campanha', $campaign]), dadosFormularioEvento())->assertSessionHasNoErrors();
    $this->assertDatabaseHas('evento', ['id_evento' => $campaign, 'data_hora_evento' => '2026-11-25 01:45:32']);
});
