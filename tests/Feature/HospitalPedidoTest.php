<?php

use App\Models\Hemocentro;
use App\Models\Hospital;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

test('pagina as requisições do hospital de cinco em cinco', function () {
    $hospital = Hospital::factory()->create();
    $destino = Hemocentro::factory()->create();
    foreach (range(1, 6) as $quantidade) {
        DB::table('pedido')->insert([
            'cnpj_hosp' => $hospital->cnpj_hosp, 'cnes_hem' => $destino->cnes_hem,
            'desc_ped' => 'Pedido de bolsas', 'quant_ped' => $quantidade,
        ]);
    }

    $pagina = $this->actingAs($hospital, 'hospital')->get(route('hospital.pedidos'))
        ->assertOk()->assertSeeText('Mostrando 1–5 de 6 requisições')->assertDontSeeText('1 unid.');
    $proxima = HTMLDocument::createFromString($pagina->getContent(), LIBXML_NOERROR)->querySelector('a[rel="next"]');
    $this->get($proxima->getAttribute('href'))->assertOk()->assertSeeText('Mostrando 6–6 de 6 requisições')
        ->assertSeeText('1 unid.')->assertDontSeeText('2 unid.')->assertSee('rel="prev"', false);
});

test('envia a requisição do hospital para o hemocentro escolhido e protege o documento', function () {
    Storage::fake('local');
    $hospital = Hospital::factory()->create();
    $destino = Hemocentro::factory()->create();
    $externo = Hemocentro::factory()->create();
    $pagina = $this->actingAs($hospital, 'hospital')->get(route('hospital.pedidos'))->assertOk()
        ->assertSeeText('Nenhuma requisição registrada.');
    $form = HTMLDocument::createFromString($pagina->getContent(), LIBXML_NOERROR)->querySelector('#formNovaRequisicao form');
    expect($form->getAttribute('method'))->toBe('POST');
    expect($form->getAttribute('enctype'))->toBe('multipart/form-data');
    $pdf = UploadedFile::fake()->createWithContent('assinado.pdf', "%PDF-1.4\n%%EOF");

    $this->post($form->getAttribute('action'), [
        'tipo_sanguineo' => 'AB+', 'urgencia' => 'critica', 'quantidade' => 4,
        'paciente_setor' => 'UTI', 'hemocentro' => $destino->cnes_hem, 'documento' => $pdf,
        'cnpj_hosp' => '00000000000000', 'status_ped' => 'entregue', 'cod_reg_fun' => 123,
    ])->assertRedirectToRoute('hospital.pedidos')->assertSessionHasNoErrors();

    $this->assertDatabaseHas('pedido', [
        'cnpj_hosp' => $hospital->cnpj_hosp, 'cnes_hem' => $destino->cnes_hem, 'cod_reg_fun' => null,
        'tipo_sanguineo_ped' => 'AB+', 'quant_ped' => 4, 'urgencia_ped' => 'critica',
        'paciente_setor_ped' => 'UTI', 'status_ped' => 'pendente',
    ]);
    $pedido = DB::table('pedido')->sole();
    Storage::disk('local')->assertExists($pedido->url_requisicao_transfusao_ped);
    $documentoHospital = route('hospital.pedidos.requisicao', $pedido->id_ped);
    $pagina = $this->get(route('hospital.pedidos'))->assertSeeText("Requisição #{$pedido->id_ped} enviada com sucesso.")
        ->assertSee('id="statPending">1<', false)->assertSee($documentoHospital);
    $linha = HTMLDocument::createFromString($pagina->getContent(), LIBXML_NOERROR)->querySelector('.requests-table tbody tr');
    expect($linha->textContent)->toContain($destino->cnes_hem, 'AB+', '4 unid.', 'Crítica', 'Recebido');
    expect($linha->querySelector('.timeline__dot--current')->getAttribute('title'))->toBe('Recebido');
    $this->get($documentoHospital)->assertOk()->assertStreamedContent("%PDF-1.4\n%%EOF");
    $this->actingAs(Hospital::factory()->create(), 'hospital')->get(route('hospital.pedidos'))
        ->assertSeeText('Nenhuma requisição registrada.')->assertDontSee($documentoHospital);
    $this->get($documentoHospital)->assertNotFound();
    $documento = route('hemocentro.pedidos.requisicao', $pedido->id_ped);
    $this->actingAs($destino, 'hemocentro')->get(route('hemocentro.pedidos'))->assertOk()->assertSee($documento);
    $this->get($documento)->assertOk()->assertStreamedContent("%PDF-1.4\n%%EOF");
    $this->actingAs($externo, 'hemocentro')->get($documento)->assertNotFound();
});

test('registra pedido sem documento ou paciente informado', function () {
    $hospital = Hospital::factory()->create();
    $destino = Hemocentro::factory()->create();

    $this->actingAs($hospital, 'hospital')->post(route('hospital.pedidos.store'), [
        'tipo_sanguineo' => 'O-', 'urgencia' => 'rotina', 'quantidade' => 1, 'hemocentro' => $destino->cnes_hem,
    ])->assertRedirectToRoute('hospital.pedidos')->assertSessionHasNoErrors();

    $this->assertDatabaseHas('pedido', ['cnpj_hosp' => $hospital->cnpj_hosp, 'paciente_setor_ped' => null, 'url_requisicao_transfusao_ped' => null]);
    $this->get(route('hospital.pedidos'))->assertSeeText('Sem documento');
});

test('mostra a etapa registrada e o documento correspondente no acompanhamento', function (string $status, string $rotulo, ?string $etapa) {
    $hospital = Hospital::factory()->create();
    $destino = Hemocentro::factory()->create();
    $documento = 'https://hospital.example.test/requisicoes/42.pdf';
    DB::table('pedido')->insert([
        'cnpj_hosp' => $hospital->cnpj_hosp, 'cnes_hem' => $destino->cnes_hem,
        'desc_ped' => 'Pedido de bolsas O+', 'quant_ped' => 2, 'status_ped' => $status,
        'urgencia_ped' => 'urgente', 'url_requisicao_transfusao_ped' => $documento,
    ]);

    $pagina = $this->actingAs($hospital, 'hospital')->get(route('hospital.pedidos'))->assertOk();
    $linha = HTMLDocument::createFromString($pagina->getContent(), LIBXML_NOERROR)->querySelector('.requests-table tbody tr');
    expect($linha->querySelector('.timeline__label')->textContent)->toBe($rotulo);
    expect($linha->querySelector('.timeline__dot--current')?->getAttribute('title'))->toBe($etapa);
    expect($linha->querySelector('a')->getAttribute('href'))->toBe($documento);
})->with([
    ['em_separacao', 'Em Separação', 'Em Separação'],
    ['enviado', 'Enviado', 'Enviado'],
    ['entregue', 'Entregue', 'Entregue'],
    ['recusado', 'Recusado', null],
]);

test('recusa requisições sem login ou com campos e documento inválidos', function () {
    $enviar = route('hospital.pedidos.store');
    $this->post($enviar)->assertRedirectToRoute('hospital.login');
    $hospital = Hospital::factory()->create();

    $this->actingAs($hospital, 'hospital')->from(route('hospital.pedidos'))->post($enviar, [
        'tipo_sanguineo' => 'X', 'urgencia' => 'alta', 'quantidade' => 0, 'hemocentro' => '9999999',
        'paciente_setor' => str_repeat('a', 256), 'documento' => UploadedFile::fake()->create('assinado.pdf', 1, 'text/plain'),
    ])->assertRedirectToRoute('hospital.pedidos')->assertSessionHasErrors([
        'tipo_sanguineo', 'urgencia', 'quantidade', 'hemocentro', 'paciente_setor', 'documento',
    ]);
    $this->withCookie(config('session.cookie'), session()->getId())->get(route('hospital.pedidos'))
        ->assertOk()->assertSeeText('Selecione um hemocentro cadastrado.');
    $this->assertDatabaseEmpty('pedido');
});
