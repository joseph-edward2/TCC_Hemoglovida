<?php

use App\Enums\CargoFuncionario;
use App\Models\Funcionario;
use App\Models\Hemocentro;
use App\Models\Hospital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('o hospital visualiza apenas os funcionários da sua unidade na gestão', function () {
    $hospital = Hospital::factory()->create([
        'cnpj_hosp' => '11222333000181',
        'nome_hosp' => 'Hospital Santa Clara',
    ]);
    $outroHospital = Hospital::factory()->create();

    $funcionario = Funcionario::factory()->paraHospital($hospital)->create([
        'cpf_fun' => '52998224725',
        'nome_fun' => 'Ana Paula Souza',
        'tipo_fun' => CargoFuncionario::Responsavel->value,
    ]);
    Funcionario::factory()->paraHospital($outroHospital)->create([
        'cpf_fun' => '28001238938',
        'nome_fun' => 'Funcionário de Outro Hospital',
    ]);

    $this->actingAs($hospital, 'hospital')
        ->get(route('hospital.funcionarios'))
        ->assertOk()
        ->assertViewIs('hospital.Hospital_funcionarios')
        ->assertViewHas('funcionarios', fn ($funcionarios) => $funcionarios->contains($funcionario))
        ->assertViewHas('funcionarios', fn ($funcionarios) => $funcionarios->every(fn ($item) => $item->relationLoaded('hospital')))
        ->assertSeeText('Gestão de Funcionários')
        ->assertSeeText('Ana Paula Souza')
        ->assertSeeText('Hospital Santa Clara')
        ->assertSeeText('CNPJ 11.222.333/0001-81')
        ->assertDontSeeText('Funcionário de Outro Hospital');
});

test('a tabela do hospital mascara o CPF e exibe o ID do funcionário', function () {
    $hospital = Hospital::factory()->create();
    $funcionario = Funcionario::factory()->paraHospital($hospital)->create([
        'cpf_fun' => '52998224725',
        'nome_fun' => 'Ana Paula Souza',
    ]);

    $this->actingAs($hospital, 'hospital')
        ->get(route('hospital.funcionarios'))
        ->assertOk()
        ->assertSeeText('ID')
        ->assertSee('<td class="func-id">'.$funcionario->getKey().'</td>', false)
        ->assertSeeText('***.982.247-**')
        ->assertDontSeeText('529.982.247-25');
});

test('o responsável do hospital acessa a gestão da própria unidade', function () {
    $hospital = Hospital::factory()->create();
    $responsavel = Funcionario::factory()->paraHospital($hospital)->create([
        'cpf_fun' => '52998224725',
        'tipo_fun' => CargoFuncionario::Responsavel->value,
    ]);

    $this->actingAs($responsavel, 'funcionario')
        ->get(route('hospital.funcionarios'))
        ->assertOk()
        ->assertSeeText('Gestão de Funcionários');
});

test('o hospital cadastra um funcionário vinculado à sua unidade', function () {
    $hospital = Hospital::factory()->create(['cnpj_hosp' => '11222333000181']);

    $this->actingAs($hospital, 'hospital')
        ->post(route('hospital.funcionarios.store'), [
            'nome_funcionario' => 'João da Silva',
            'cpf_funcionario' => '529.982.247-25',
            'senha_funcionario' => 'senha-segura',
            'tipo_funcionario' => CargoFuncionario::Administrativo->value,
        ])
        ->assertRedirectToRoute('hospital.funcionarios')
        ->assertSessionHas('status', 'Funcionário cadastrado com sucesso.');

    $funcionario = Funcionario::where('cpf_fun', '52998224725')->firstOrFail();

    expect($funcionario->cnpj_hosp)->toBe('11222333000181')
        ->and($funcionario->cnes_hem)->toBeNull()
        ->and($funcionario->nome_fun)->toBe('João da Silva')
        ->and($funcionario->tipo_fun)->toBe(CargoFuncionario::Administrativo)
        ->and(Hash::check('senha-segura', $funcionario->senha_fun))->toBeTrue();
});

test('o responsável cadastra funcionário ignorando a instituição enviada no payload', function () {
    $hospital = Hospital::factory()->create(['cnpj_hosp' => '11222333000181']);
    $responsavel = Funcionario::factory()->paraHospital($hospital)->create([
        'cpf_fun' => '28001238938',
        'tipo_fun' => CargoFuncionario::Responsavel->value,
    ]);
    $hemocentro = Hemocentro::factory()->create();

    $this->actingAs($responsavel, 'funcionario')
        ->post(route('hospital.funcionarios.store'), [
            'nome_funcionario' => 'Maria Souza',
            'cpf_funcionario' => '12345678909',
            'senha_funcionario' => 'senha-segura',
            'tipo_funcionario' => CargoFuncionario::Responsavel->value,
            'cnpj_hosp' => '99999999999999',
            'cnes_hem' => $hemocentro->cnes_hem,
        ])
        ->assertRedirectToRoute('hospital.funcionarios');

    $criado = Funcionario::where('cpf_fun', '12345678909')->firstOrFail();

    expect($criado->cnpj_hosp)->toBe('11222333000181')
        ->and($criado->cnes_hem)->toBeNull();
});

test('o hospital atualiza nome cargo e senha sem alterar o CPF', function () {
    $hospital = Hospital::factory()->create();
    $funcionario = Funcionario::factory()->paraHospital($hospital)->create([
        'cpf_fun' => '52998224725',
        'nome_fun' => 'Nome Antigo',
        'tipo_fun' => CargoFuncionario::Administrativo->value,
    ]);

    $this->actingAs($hospital, 'hospital')
        ->put(route('hospital.funcionarios.update', $funcionario), [
            'nome_funcionario' => 'Nome Novo',
            'tipo_funcionario' => CargoFuncionario::Responsavel->value,
            'senha_funcionario' => 'senha-nova-segura',
            'senha_funcionario_confirmation' => 'senha-nova-segura',
            'cpf_funcionario' => '28001238938',
            'cnpj_hosp' => '99999999999999',
        ])
        ->assertRedirectToRoute('hospital.funcionarios')
        ->assertSessionHas('status', 'Funcionário atualizado com sucesso.');

    $funcionario->refresh();

    expect($funcionario->nome_fun)->toBe('Nome Novo')
        ->and($funcionario->tipo_fun)->toBe(CargoFuncionario::Responsavel)
        ->and($funcionario->cpf_fun)->toBe('52998224725')
        ->and($funcionario->cnpj_hosp)->not->toBe('99999999999999')
        ->and(Hash::check('senha-nova-segura', $funcionario->senha_fun))->toBeTrue();
});

test('o hospital mantém a senha atual quando o campo é deixado em branco', function () {
    $hospital = Hospital::factory()->create();
    $funcionario = Funcionario::factory()->paraHospital($hospital)->create([
        'cpf_fun' => '52998224725',
        'senha_fun' => 'senha-original',
    ]);
    $senhaOriginal = $funcionario->senha_fun;

    $this->actingAs($hospital, 'hospital')
        ->put(route('hospital.funcionarios.update', $funcionario), [
            'nome_funcionario' => 'Nome Atualizado',
            'tipo_funcionario' => CargoFuncionario::Administrativo->value,
            'senha_funcionario' => '',
        ])
        ->assertRedirectToRoute('hospital.funcionarios');

    expect($funcionario->refresh()->senha_fun)->toBe($senhaOriginal);
});

test('o hospital exclui um funcionário da própria unidade', function () {
    $hospital = Hospital::factory()->create();
    $funcionario = Funcionario::factory()->paraHospital($hospital)->create([
        'cpf_fun' => '52998224725',
    ]);

    $this->actingAs($hospital, 'hospital')
        ->delete(route('hospital.funcionarios.destroy', $funcionario))
        ->assertRedirectToRoute('hospital.funcionarios')
        ->assertSessionHas('status', 'Funcionário removido com sucesso.');

    $this->assertModelMissing($funcionario);
});

test('o funcionário não pode excluir a própria conta', function () {
    $hospital = Hospital::factory()->create();
    $funcionario = Funcionario::factory()->paraHospital($hospital)->create([
        'cpf_fun' => '52998224725',
        'tipo_fun' => CargoFuncionario::Responsavel->value,
    ]);

    $this->actingAs($funcionario, 'funcionario')
        ->delete(route('hospital.funcionarios.destroy', $funcionario))
        ->assertUnprocessable()
        ->assertSeeText('Você não pode excluir seu próprio usuário.');

    $this->assertModelExists($funcionario);
});

test('o hospital não altera nem exclui funcionário de outra unidade', function (string $metodo, string $rota) {
    $hospital = Hospital::factory()->create();
    $outroHospital = Hospital::factory()->create();
    $funcionario = Funcionario::factory()->paraHospital($outroHospital)->create([
        'cpf_fun' => '52998224725',
        'nome_fun' => 'Nome Original',
    ]);

    $this->actingAs($hospital, 'hospital')
        ->{$metodo}(route($rota, $funcionario), [
            'nome_funcionario' => 'Nome Alterado',
            'tipo_funcionario' => CargoFuncionario::Responsavel->value,
        ])
        ->assertNotFound();

    expect($funcionario->refresh()->nome_fun)->toBe('Nome Original');
})->with([
    'edição' => ['put', 'hospital.funcionarios.update'],
    'exclusão' => ['delete', 'hospital.funcionarios.destroy'],
]);

test('o responsável não altera nem exclui funcionário de outro hospital', function (string $metodo, string $rota) {
    $hospital = Hospital::factory()->create();
    $outroHospital = Hospital::factory()->create();
    $responsavel = Funcionario::factory()->paraHospital($hospital)->create([
        'cpf_fun' => '28001238938',
        'tipo_fun' => CargoFuncionario::Responsavel->value,
    ]);
    $funcionario = Funcionario::factory()->paraHospital($outroHospital)->create([
        'cpf_fun' => '52998224725',
        'nome_fun' => 'Nome Original',
    ]);

    $this->actingAs($responsavel, 'funcionario')
        ->{$metodo}(route($rota, $funcionario), [
            'nome_funcionario' => 'Nome Alterado',
            'tipo_funcionario' => CargoFuncionario::Responsavel->value,
        ])
        ->assertNotFound();

    expect($funcionario->refresh()->nome_fun)->toBe('Nome Original');
})->with([
    'edição' => ['put', 'hospital.funcionarios.update'],
    'exclusão' => ['delete', 'hospital.funcionarios.destroy'],
]);

test('o funcionário administrativo recebe 403 na listagem e no cadastro', function (string $metodo, string $rota) {
    $hospital = Hospital::factory()->create();
    $administrativo = Funcionario::factory()->paraHospital($hospital)->create([
        'cpf_fun' => '52998224725',
        'tipo_fun' => CargoFuncionario::Administrativo->value,
    ]);

    $this->actingAs($administrativo, 'funcionario')
        ->{$metodo}(route($rota))
        ->assertForbidden();
})->with([
    'listagem' => ['get', 'hospital.funcionarios'],
    'cadastro' => ['post', 'hospital.funcionarios.store'],
]);

test('o funcionário administrativo recebe 403 na edição e na exclusão', function (string $metodo, string $rota) {
    $hospital = Hospital::factory()->create();
    $administrativo = Funcionario::factory()->paraHospital($hospital)->create([
        'cpf_fun' => '52998224725',
        'tipo_fun' => CargoFuncionario::Administrativo->value,
    ]);
    $outro = Funcionario::factory()->paraHospital($hospital)->create([
        'cpf_fun' => '12345678909',
    ]);

    $this->actingAs($administrativo, 'funcionario')
        ->{$metodo}(route($rota, $outro))
        ->assertForbidden();
})->with([
    'edição' => ['put', 'hospital.funcionarios.update'],
    'exclusão' => ['delete', 'hospital.funcionarios.destroy'],
]);

test('rejeita dados inválidos na edição de funcionário', function (array $overrides, string $campo) {
    $hospital = Hospital::factory()->create();
    $funcionario = Funcionario::factory()->paraHospital($hospital)->create([
        'cpf_fun' => '52998224725',
    ]);

    $this->actingAs($hospital, 'hospital')
        ->put(route('hospital.funcionarios.update', $funcionario), array_merge([
            'nome_funcionario' => 'Nome Válido',
            'tipo_funcionario' => CargoFuncionario::Administrativo->value,
            'senha_funcionario' => '',
        ], $overrides))
        ->assertSessionHasErrors($campo);
})->with([
    'sem nome' => [['nome_funcionario' => ''], 'nome_funcionario'],
    'cargo inválido' => [['tipo_funcionario' => 'Inexistente'], 'tipo_funcionario'],
    'senha curta' => [['senha_funcionario' => '123'], 'senha_funcionario'],
    'confirmação divergente' => [['senha_funcionario' => 'senha-segura', 'senha_funcionario_confirmation' => 'outra-senha'], 'senha_funcionario'],
]);

test('os aliases antigos continuam apontando para a nova gestão', function () {
    $hospital = Hospital::factory()->create();

    $this->actingAs($hospital, 'hospital')
        ->get(route('hospital.cadastro-funcionario'))
        ->assertOk()
        ->assertViewIs('hospital.Hospital_funcionarios');

    $this->post(route('hospital.funcionario.store'), [
        'nome_funcionario' => 'João da Silva',
        'cpf_funcionario' => '52998224725',
        'senha_funcionario' => 'senha-segura',
        'tipo_funcionario' => CargoFuncionario::Administrativo->value,
    ])->assertRedirectToRoute('hospital.funcionarios');

    expect(Funcionario::where('cpf_fun', '52998224725')->count())->toBe(1);
});
