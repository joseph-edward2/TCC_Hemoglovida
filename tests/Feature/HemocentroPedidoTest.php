<?php

use App\Models\Funcionario;
use App\Models\Hemocentro;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);

test('mostra os pedidos, as etapas e os totais do hemocentro nas duas formas de acesso', function (string $guard) {
    $this->travelTo(Carbon::parse('2026-11-25 02:59:59', 'UTC'));
    $hemocentro = Hemocentro::factory()->create();
    $funcionario = Funcionario::factory()->create(['cnes_hem' => $hemocentro->cnes_hem, 'nome_fun' => 'Técnico <Local>']);
    $externo = Funcionario::factory()->create(['nome_fun' => 'Técnico externo']);
    $critico = criarPedidoHospitalar($funcionario, [
        'status_ped' => 'em_separacao', 'urgencia_ped' => 'critica', 'tipo_sanguineo_ped' => 'O-',
        'quant_ped' => 3, 'paciente_setor_ped' => 'Setor <UTI>', 'desc_ped' => 'Descrição <reservada>',
        'url_requisicao_transfusao_ped' => 'https://hospital.example.test/requisicoes/42.pdf',
        'data_hora_ped' => '2026-11-25 00:15:00',
    ]);
    $pendente = criarPedidoHospitalar($funcionario, [
        'desc_ped' => 'Descrição antiga', 'cod_reg_fun' => $externo->cod_reg_fun,
        'url_requisicao_transfusao_ped' => 'data:text/plain,anexo-invalido',
    ]);
    $entregue = criarPedidoHospitalar($funcionario, [
        'status_ped' => 'entregue', 'urgencia_ped' => 'critica', 'data_hora_ped' => '2026-11-20 12:00:00',
        'data_hora_envio_ped' => '2026-11-24 03:00:00', 'url_requisicao_transfusao_ped' => '/storage/requisicoes/local.pdf',
    ]);
    criarPedidoHospitalar($funcionario, ['status_ped' => 'enviado', 'urgencia_ped' => 'critica', 'data_hora_envio_ped' => '2026-11-24 02:59:59']);
    criarPedidoHospitalar($funcionario, ['status_ped' => 'enviado', 'data_hora_envio_ped' => '2026-11-25 02:59:59']);
    criarPedidoHospitalar($funcionario, ['status_ped' => 'enviado', 'data_hora_envio_ped' => '2026-11-25 03:00:00']);
    criarPedidoHospitalar($funcionario, ['status_ped' => 'enviado']);
    criarPedidoHospitalar($externo, ['desc_ped' => 'Pedido externo', 'urgencia_ped' => 'critica']);

    $resposta = $this->actingAs($guard === 'hemocentro' ? $hemocentro : $funcionario, $guard)
        ->get(route('hemocentro.pedidos'))
        ->assertOk()
        ->assertSee(['id="statPending">1<', 'id="statSeparating">1<', 'id="statCritical">2<', 'id="statSentToday">2<'], false)
        ->assertSeeText(['3 bolsas', '24/11/2026 21:15', '1 bolsa', 'Não informada', 'Descrição antiga'])
        ->assertSee(['Hospital <Municipal>', 'Setor <UTI>', 'Descrição <reservada>', 'Técnico <Local>'])
        ->assertDontSee(['Setor <UTI>', 'Descrição <reservada>', 'Técnico externo', 'Pedido externo', 'data:text/plain'], false);

    $pagina = HTMLDocument::createFromString($resposta->getContent(), LIBXML_NOERROR);
    expect($pagina->querySelector("#criticalList #pedido-{$critico} [aria-current=step]")->textContent)->toContain('Em Separação');
    expect($pagina->querySelector("#detalhes-{$critico} a")->getAttribute('href'))->toBe('https://hospital.example.test/requisicoes/42.pdf');
    expect($pagina->querySelector("#recentList #detalhes-{$entregue} a")->getAttribute('href'))->toBe(url('/storage/requisicoes/local.pdf'));
    expect($pagina->querySelector("#recentList #detalhes-{$pendente}")->textContent)->toContain('Não informado');
})->with(['hemocentro', 'funcionario']);

test('combina os três filtros e carrega a próxima página da lista na ordem dos protocolos', function (string $urgencia, string $lista) {
    $funcionario = Funcionario::factory()->create();
    $dados = ['urgencia_ped' => $urgencia, 'tipo_sanguineo_ped' => 'AB+'];
    foreach (range(1, 6) as $numero) {
        criarPedidoHospitalar($funcionario, ['desc_ped' => "Correspondente {$numero}"] + $dados);
    }
    criarPedidoHospitalar($funcionario, ['desc_ped' => 'Outro tipo', 'tipo_sanguineo_ped' => 'O-'] + $dados);
    criarPedidoHospitalar($funcionario, ['desc_ped' => 'Outra urgência', 'urgencia_ped' => 'urgente'] + $dados);
    criarPedidoHospitalar($funcionario, ['desc_ped' => 'Outro status', 'status_ped' => 'entregue'] + $dados);
    $filtros = ['status' => 'pendente', 'tipo_sanguineo' => 'AB+', 'urgencia' => $urgencia];

    $resposta = $this->actingAs($funcionario, 'funcionario')->get(route('hemocentro.pedidos', $filtros))
        ->assertOk()
        ->assertSeeTextInOrder(['Correspondente 6', 'Correspondente 5', 'Correspondente 4', 'Correspondente 3', 'Correspondente 2'])
        ->assertDontSeeText(['Correspondente 1', 'Outro tipo', 'Outra urgência', 'Outro status'])
        ->assertSee('id="statPending">8<', false);
    $pagina = HTMLDocument::createFromString($resposta->getContent(), LIBXML_NOERROR);
    $proxima = $pagina->querySelector("a[data-lista={$lista}]")->getAttribute('href');

    $this->get($proxima)->assertOk()->assertSeeText('Correspondente 1')
        ->assertDontSeeText(['Correspondente 2', 'Outro tipo', 'Outra urgência', 'Outro status'])
        ->assertDontSee('data-lista="'.$lista.'"', false);
})->with(['críticos' => ['critica', 'criticalList'], 'recentes' => ['rotina', 'recentList']]);

test('registra quem aceitou e conta o envio uma vez mesmo ao repetir a ação depois da meia-noite', function () {
    $this->travelTo(Carbon::parse('2026-11-25 02:59:00', 'UTC'));
    $responsavel = Funcionario::factory()->create();
    $tecnico = Funcionario::factory()->create(['cnes_hem' => $responsavel->cnes_hem]);
    $pedido = criarPedidoHospitalar($responsavel, ['urgencia_ped' => 'critica']);
    $acao = route('hemocentro.pedidos.status', $pedido);
    $pagina = route('hemocentro.pedidos', ['urgencia' => 'critica']);

    $this->actingAs($tecnico, 'funcionario')->from($pagina)->patch($acao, ['status' => 'em_separacao'])
        ->assertRedirect($pagina)->assertSessionHasNoErrors();
    $this->assertDatabaseHas('pedido', ['id_ped' => $pedido, 'status_ped' => 'em_separacao', 'cod_reg_fun' => $tecnico->cod_reg_fun, 'data_hora_envio_ped' => null]);

    $this->patch($acao, ['status' => 'enviado'])->assertRedirect($pagina)->assertSessionHasNoErrors();
    $this->get($pagina)->assertOk()->assertSeeText('24/11/2026 23:59')->assertSee('id="statSentToday">1<', false);

    $this->travel(2)->minutes();
    $this->patch($acao, ['status' => 'enviado'])->assertRedirect($pagina)->assertSessionHasNoErrors();
    $this->assertDatabaseHas('pedido', ['id_ped' => $pedido, 'status_ped' => 'enviado', 'data_hora_envio_ped' => '2026-11-25 02:59:00']);
    $this->get($pagina)->assertOk()->assertSee('id="statSentToday">0<', false);
});

test('mantém o técnico do pedido quando a conta do hemocentro aceita a separação', function () {
    $hemocentro = Hemocentro::factory()->create();
    $funcionario = Funcionario::factory()->create(['cnes_hem' => $hemocentro->cnes_hem]);
    $pedido = criarPedidoHospitalar($funcionario);

    $this->actingAs($hemocentro, 'hemocentro')->patch(route('hemocentro.pedidos.status', $pedido), ['status' => 'em_separacao'])
        ->assertRedirectToRoute('hemocentro.pedidos')->assertSessionHasNoErrors();
    $this->assertDatabaseHas('pedido', ['id_ped' => $pedido, 'status_ped' => 'em_separacao', 'cod_reg_fun' => $funcionario->cod_reg_fun]);
});

test('preserva o pedido recusado no histórico e o retira da fila crítica', function () {
    $funcionario = Funcionario::factory()->create();
    $pedido = criarPedidoHospitalar($funcionario, ['urgencia_ped' => 'critica', 'desc_ped' => 'Pedido para recusar']);

    $this->actingAs($funcionario, 'funcionario')->patch(route('hemocentro.pedidos.status', $pedido), ['status' => 'recusado'])
        ->assertRedirectToRoute('hemocentro.pedidos')->assertSessionHasNoErrors();
    $this->assertDatabaseHas('pedido', ['id_ped' => $pedido, 'status_ped' => 'recusado', 'data_hora_envio_ped' => null]);
    $this->get(route('hemocentro.pedidos', ['status' => 'recusado']))->assertOk()
        ->assertSeeTextInOrder(['Nenhum pedido crítico encontrado.', 'Pedidos Recentes', 'Pedido para recusar'])
        ->assertSee(['id="statPending">0<', 'id="statCritical">0<'], false)
        ->assertDontSeeText('Aceitar e Separar');
});

test('preserva o status quando o pedido já não está na etapa que permite a ação', function (string $atual, string $destino) {
    $funcionario = Funcionario::factory()->create();
    $pedido = criarPedidoHospitalar($funcionario, ['status_ped' => $atual]);

    $this->actingAs($funcionario, 'funcionario')->patch(route('hemocentro.pedidos.status', $pedido), ['status' => $destino])
        ->assertRedirectToRoute('hemocentro.pedidos')->assertSessionHasErrors(['pedido' => 'O status atual do pedido não permite esta ação.']);
    $this->assertDatabaseHas('pedido', ['id_ped' => $pedido, 'status_ped' => $atual, 'data_hora_envio_ped' => null]);
})->with([
    'enviar antes de separar' => ['pendente', 'enviado'],
    'recusar após o aceite' => ['em_separacao', 'recusado'],
    'reabrir após a recusa' => ['recusado', 'em_separacao'],
    'reenviar após a entrega' => ['entregue', 'enviado'],
]);

test('retorna 404 ao tentar alterar um pedido de outro hemocentro', function () {
    $funcionario = Funcionario::factory()->create();
    $externo = Funcionario::factory()->create();
    $pedido = criarPedidoHospitalar($externo);

    $this->actingAs($funcionario, 'funcionario')->patch(route('hemocentro.pedidos.status', $pedido), [
        'status' => 'recusado', 'cnes_hem' => $externo->cnes_hem,
    ])->assertNotFound();
    $this->assertDatabaseHas('pedido', ['id_ped' => $pedido, 'status_ped' => 'pendente']);
});

test('rejeita uma ação de status desconhecida', function () {
    $funcionario = Funcionario::factory()->create();
    $pedido = criarPedidoHospitalar($funcionario);

    $this->actingAs($funcionario, 'funcionario')->from(route('hemocentro.pedidos'))
        ->patch(route('hemocentro.pedidos.status', $pedido), ['status' => 'invalido'])
        ->assertSessionHasErrors(['status' => 'Selecione uma ação válida para o pedido.']);
    $this->assertDatabaseHas('pedido', ['id_ped' => $pedido, 'status_ped' => 'pendente']);
});

test('exige login para consultar e alterar os pedidos', function () {
    $this->get(route('hemocentro.pedidos'))->assertRedirectToRoute('hemocentro.login');
    $this->patch(route('hemocentro.pedidos.status', 1), ['status' => 'recusado'])->assertRedirectToRoute('hemocentro.login');
});

/**
 * @param  array{status_ped?: string, urgencia_ped?: string, tipo_sanguineo_ped?: string, paciente_setor_ped?: string, quant_ped?: int, desc_ped?: string, cod_reg_fun?: int, data_hora_ped?: string, data_hora_envio_ped?: string, url_requisicao_transfusao_ped?: string}  $dados
 */
function criarPedidoHospitalar(Funcionario $funcionario, array $dados = []): int
{
    $cnpj = '00000000000001';
    DB::table('hospital')->insertOrIgnore([
        'cnpj_hosp' => $cnpj, 'cnes_hosp' => '0000001', 'nome_hosp' => 'Hospital <Municipal>',
        'contato_hosp' => '11900000000', 'endereco_hosp' => 'Rua de teste', 'senha_hosp' => 'hash-de-teste',
    ]);

    return DB::table('pedido')->insertGetId($dados + [
        'cnpj_hosp' => $cnpj, 'cnes_hem' => $funcionario->cnes_hem, 'cod_reg_fun' => $funcionario->cod_reg_fun,
        'desc_ped' => 'Pedido de teste', 'quant_ped' => 1, 'data_hora_ped' => '2026-11-24 12:00:00',
    ], 'id_ped');
}
