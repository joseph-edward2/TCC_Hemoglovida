<?php

use App\Models\Funcionario;
use App\Models\Hemocentro;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

function createTriageBag(string $cnes, string $isbt, string $status = 'em_analise', ?string $report = null): void
{
    DB::table('bolsa_sangue')->insert([
        'isbt' => $isbt, 'cnes_hem' => $cnes, 'quant_bolsa' => 1, 'tipo_bolsa' => 'AB+',
        'disponibilidade_bolsa' => $status, 'laudo_path' => $report,
    ]);
}

test('lista as bolsas em análise do hemocentro e exige laudo para as duas decisões', function (string $status) {
    $center = Hemocentro::factory()->create();
    createTriageBag($center->cnes_hem, 'SEM-LAUDO');
    createTriageBag($center->cnes_hem, 'OUTRA-PENDENTE', 'Em análise');
    createTriageBag($center->cnes_hem, 'JA-LIBERADA', 'disponivel');
    createTriageBag(Hemocentro::factory()->create()->cnes_hem, 'OUTRO-CENTRO');

    $this->actingAs($center, 'hemocentro')->get(route('hemocentro.triagem'))
        ->assertOk()->assertSeeText(['SEM-LAUDO', 'OUTRA-PENDENTE', 'AB+'])
        ->assertDontSeeText(['JA-LIBERADA', 'OUTRO-CENTRO'])
        ->assertSeeInOrder(['value="disponivel" class="release-btn is-disabled" disabled', 'value="recusada" class="release-btn is-disabled" disabled'], false);

    $this->post(route('hemocentro.triagem.concluir', 'SEM-LAUDO'), compact('status'))
        ->assertSessionHasErrors('laudo');
    $this->assertDatabaseHas('bolsa_sangue', ['isbt' => 'SEM-LAUDO', 'disponibilidade_bolsa' => 'em_analise']);
})->with(['disponivel', 'recusada']);

test('persiste o laudo e a decisão do funcionário e atualiza a triagem e o inventário', function (string $status, string $label) {
    Storage::fake('local');
    $employee = Funcionario::factory()->create();
    createTriageBag($employee->cnes_hem, 'ISBT-1');
    $contents = "%PDF-1.4\n%%EOF";
    $report = UploadedFile::fake()->createWithContent('laudo.pdf', $contents);

    $this->actingAs($employee, 'funcionario')->post(route('hemocentro.triagem.anexar', 'ISBT-1'), ['laudo' => $report])
        ->assertRedirectToRoute('hemocentro.triagem')->assertSessionHasNoErrors();
    $this->get(route('hemocentro.triagem'))->assertOk()->assertSeeText('Ver Documento')
        ->assertSeeInOrder(['value="disponivel" class="release-btn is-enabled"', 'value="recusada" class="release-btn is-enabled"'], false)
        ->assertDontSee('is-disabled');
    $this->get(route('hemocentro.triagem.laudo', 'ISBT-1'))->assertOk()->assertStreamedContent($contents);
    $this->post(route('hemocentro.triagem.anexar', 'ISBT-1'), ['laudo' => $report])->assertNotFound();
    $this->post(route('hemocentro.triagem.concluir', 'ISBT-1'), ['status' => 'reservado'])->assertSessionHasErrors('status');
    $this->post(route('hemocentro.triagem.concluir', 'ISBT-1'), compact('status'))->assertRedirectToRoute('hemocentro.triagem');
    $this->post(route('hemocentro.triagem.concluir', 'ISBT-1'), ['status' => $status === 'disponivel' ? 'recusada' : 'disponivel'])->assertNotFound();

    $this->assertDatabaseHas('bolsa_sangue', ['isbt' => 'ISBT-1', 'disponibilidade_bolsa' => $status]);
    $this->get(route('hemocentro.triagem'))->assertOk()->assertSeeText('Nenhuma bolsa em análise.')->assertDontSeeHtml('<div class="triage-code">ISBT-1</div>');
    $this->get(route('hemocentro.estoque'))->assertOk()->assertSeeInOrder(['ISBT-1', $label]);
})->with([['disponivel', 'Disponível'], ['recusada', 'Recusada']]);

test('rejeita laudos inválidos sem habilitar decisões', function () {
    Storage::fake('local');
    $center = Hemocentro::factory()->create();
    createTriageBag($center->cnes_hem, 'ISBT-2');

    $this->actingAs($center, 'hemocentro')->post(route('hemocentro.triagem.anexar', 'ISBT-2'), [
        'laudo' => UploadedFile::fake()->create('laudo.txt', 1, 'text/plain'),
    ])->assertSessionHasErrors('laudo');
    $this->assertDatabaseHas('bolsa_sangue', ['isbt' => 'ISBT-2', 'laudo_path' => null]);
    Storage::disk('local')->assertEmpty();
});

test('restringe a leitura e as alterações ao hemocentro autenticado', function () {
    $center = Hemocentro::factory()->create();
    $otherCenter = Hemocentro::factory()->create();
    createTriageBag($otherCenter->cnes_hem, 'AGUARDANDO');
    createTriageBag($otherCenter->cnes_hem, 'COM-LAUDO', report: 'laudos/teste.pdf');
    Storage::fake('local')->put('laudos/teste.pdf', 'teste');

    $this->get(route('hemocentro.triagem'))->assertRedirectToRoute('hemocentro.login');
    $this->actingAs($center, 'hemocentro')->get(route('hemocentro.triagem.laudo', 'COM-LAUDO'))->assertNotFound();
    $this->post(route('hemocentro.triagem.anexar', 'AGUARDANDO'))->assertNotFound();
    $this->post(route('hemocentro.triagem.concluir', 'COM-LAUDO'), ['status' => 'recusada'])->assertNotFound();
    $this->assertDatabaseHas('bolsa_sangue', ['isbt' => 'COM-LAUDO', 'disponibilidade_bolsa' => 'em_analise']);
});
