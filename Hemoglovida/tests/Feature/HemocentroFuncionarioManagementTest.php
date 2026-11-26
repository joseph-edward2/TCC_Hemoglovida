<?php

use App\Enums\CargoFuncionario;
use App\Models\Funcionario;
use App\Models\Hemocentro;
use App\Models\Hospital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('o hemocentro visualiza apenas os funcionários da sua unidade na gestão', function () {
    $hemocentro = Hemocentro::factory()->create([
        'cnes_hem' => '0000001',
        'nome_hem' => 'Hemocentro Central',
    ]);
    $outroHemocentro = Hemocentro::factory()->create();

    $funcionario = Funcionario::factory()->paraHemocentro($hemocentro)->create([
        'cpf_fun' => '52998224725',
        'nome_fun' => 'Carla Nunes',
        'tipo_fun' => CargoFuncionario::Responsavel->value,
    ]);
    Funcionario::factory()->paraHemocentro($outroHemocentro)->create([
        'cpf_fun' => '28001238938',
        'nome_fun' => 'Funcionário de Outro Hemocentro',
    ]);

    $this->actingAs($hemocentro, 'hemocentro')
        ->get(route('hemocentro.funcionarios'))
        ->assertOk()
        ->assertViewIs('hemocentro.Hem_funcionarios')
        ->assertViewHas('funcionarios', fn ($funcionarios) => $funcionarios->contains($funcionario))
        ->assertViewHas('funcionarios', fn ($funcionarios) => $funcionarios->every(fn ($item) => $item->relationLoaded('hemocentro')))
        ->assertSeeText('Gestão de Funcionários')
        ->assertSeeText('Carla Nunes')
        ->assertSeeText('Hemocentro Central')
        ->assertSeeText('CNES 0000001')
        ->assertDontSeeText('Funcionário de Outro Hemocentro');
});

test('a gestão do hemocentro usa o rótulo padrão quando a unidade não tem nome', function () {
    $hemocentro = Hemocentro::factory()->create([
        'cnes_hem' => '0000002',
        'nome_hem' => null,
    ]);
    Funcionario::factory()->paraHemocentro($hemocentro)->create([
        'cpf_fun' => '52998224725',
        'nome_fun' => 'Carla Nunes',
    ]);

    $this->actingAs($hemocentro, 'hemocentro')
        ->get(route('hemocentro.funcionarios'))
        ->assertOk()
        ->assertSeeText('Hemocentro Leste')
        ->assertSeeText('CNES 0000002');
});

test('a tabela do hemocentro mascara o CPF e exibe o ID do funcionário', function () {
    $hemocentro = Hemocentro::factory()->create();
    $funcionario = Funcionario::factory()->paraHemocentro($hemocentro)->create([
        'cpf_fun' => '52998224725',
        'nome_fun' => 'Carla Nunes',
    ]);

    $this->actingAs($hemocentro, 'hemocentro')
        ->get(route('hemocentro.funcionarios'))
        ->assertOk()
        ->assertSeeText('ID')
        ->assertSee('<td class="func-id">'.$funcionario->getKey().'</td>', false)
        ->assertSeeText('***.982.247-**')
        ->assertDontSeeText('529.982.247-25');
});

test('a gestão do hemocentro mantém a navegação completa da unidade', function () {
    $hemocentro = Hemocentro::factory()->create();

    $this->actingAs($hemocentro, 'hemocentro')
        ->get(route('hemocentro.funcionarios'))
        ->assertOk()
        ->assertSee('href="'.route('hemocentro.inicio').'"', false)
        ->assertSee('href="'.route('hemocentro.atendimento').'"', false)
        ->assertSee('href="'.route('hemocentro.estoque').'"', false)
        ->assertSee('href="'.route('hemocentro.agendamento').'"', false)
        ->assertSee('href="'.route('hemocentro.pedidos').'"', false)
        ->assertSee('href="'.route('hemocentro.alertas').'"', false)
        ->assertSee('class="active" href="'.route('hemocentro.funcionarios').'"', false)
        ->assertDontSeeText('Minha Conta');
});

test('o responsável do hemocentro acessa a gestão da própria unidade', function () {
    $hemocentro = Hemocentro::factory()->create();
    $responsavel = Funcionario::factory()->paraHemocentro($hemocentro)->create([
        'cpf_fun' => '52998224725',
        'tipo_fun' => CargoFuncionario::Responsavel->value,
    ]);

    $this->actingAs($responsavel, 'funcionario')
        ->get(route('hemocentro.funcionarios'))
        ->assertOk()
        ->assertSeeText('Gestão de Funcionários');
});

test('o hemocentro cadastra um funcionário vinculado à sua unidade', function () {
    $hemocentro = Hemocentro::factory()->create(['cnes_hem' => '0000001']);

    $this->actingAs($hemocentro, 'hemocentro')
        ->post(route('hemocentro.funcionarios.store'), [
            'nome_funcionario' => 'João da Silva',
            'cpf_funcionario' => '529.982.247-25',
            'senha_funcionario' => 'senha-segura',
            'tipo_funcionario' => CargoFuncionario::Administrativo->value,
        ])
        ->assertRedirectToRoute('hemocentro.funcionarios')
        ->assertSessionHas('status', 'Funcionário cadastrado com sucesso.');

    $funcionario = Funcionario::where('cpf_fun', '52998224725')->firstOrFail();

    expect($funcionario->cnes_hem)->toBe('0000001')
        ->and($funcionario->cnpj_hosp)->toBeNull()
        ->and($funcionario->nome_fun)->toBe('João da Silva')
        ->and(Hash::check('senha-segura', $funcionario->senha_fun))->toBeTrue();
});

test('o responsável cadastra funcionário ignorando a instituição enviada no payload', function () {
    $hemocentro = Hemocentro::factory()->create(['cnes_hem' => '0000001']);
    $responsavel = Funcionario::factory()->paraHemocentro($hemocentro)->create([
        'cpf_fun' => '28001238938',
        'tipo_fun' => CargoFuncionario::Responsavel->value,
    ]);
    $hospital = Hospital::factory()->create();

    $this->actingAs($responsavel, 'funcionario')
        ->post(route('hemocentro.funcionarios.store'), [
            'nome_funcionario' => 'Maria Souza',
            'cpf_funcionario' => '12345678909',
            'senha_funcionario' => 'senha-segura',
            'tipo_funcionario' => CargoFuncionario::Responsavel->value,
            'cnes_hem' => '9999999',
            'cnpj_hosp' => $hospital->cnpj_hosp,
        ])
        ->assertRedirectToRoute('hemocentro.funcionarios');

    $criado = Funcionario::where('cpf_fun', '12345678909')->firstOrFail();

    expect($criado->cnes_hem)->toBe('0000001')
        ->and($criado->cnpj_hosp)->toBeNull();
});

test('o hemocentro atualiza nome cargo e senha sem alterar o CPF', function () {
    $hemocentro = Hemocentro::factory()->create();
    $funcionario = Funcionario::factory()->paraHemocentro($hemocentro)->create([
        'cpf_fun' => '52998224725',
        'nome_fun' => 'Nome Antigo',
        'tipo_fun' => CargoFuncionario::Administrativo->value,
    ]);

    $this->actingAs($hemocentro, 'hemocentro')
        ->put(route('hemocentro.funcionarios.update', $funcionario), [
            'nome_funcionario' => 'Nome Novo',
            'tipo_funcionario' => CargoFuncionario::Responsavel->value,
            'senha_funcionario' => 'senha-nova-segura',
            'senha_funcionario_confirmation' => 'senha-nova-segura',
            'cpf_funcionario' => '28001238938',
            'cnes_hem' => '9999999',
        ])
        ->assertRedirectToRoute('hemocentro.funcionarios')
        ->assertSessionHas('status', 'Funcionário atualizado com sucesso.');

    $funcionario->refresh();

    expect($funcionario->nome_fun)->toBe('Nome Novo')
        ->and($funcionario->tipo_fun)->toBe(CargoFuncionario::Responsavel)
        ->and($funcionario->cpf_fun)->toBe('52998224725')
        ->and($funcionario->cnes_hem)->not->toBe('9999999')
        ->and(Hash::check('senha-nova-segura', $funcionario->senha_fun))->toBeTrue();
});

test('o hemocentro exclui um funcionário da própria unidade', function () {
    $hemocentro = Hemocentro::factory()->create();
    $funcionario = Funcionario::factory()->paraHemocentro($hemocentro)->create([
        'cpf_fun' => '52998224725',
    ]);

    $this->actingAs($hemocentro, 'hemocentro')
        ->delete(route('hemocentro.funcionarios.destroy', $funcionario))
        ->assertRedirectToRoute('hemocentro.funcionarios')
        ->assertSessionHas('status', 'Funcionário removido com sucesso.');

    $this->assertModelMissing($funcionario);
});

test('o funcionário não pode excluir a própria conta no hemocentro', function () {
    $hemocentro = Hemocentro::factory()->create();
    $funcionario = Funcionario::factory()->paraHemocentro($hemocentro)->create([
        'cpf_fun' => '52998224725',
        'tipo_fun' => CargoFuncionario::Responsavel->value,
    ]);

    $this->actingAs($funcionario, 'funcionario')
        ->delete(route('hemocentro.funcionarios.destroy', $funcionario))
        ->assertUnprocessable()
        ->assertSeeText('Você não pode excluir seu próprio usuário.');

    $this->assertModelExists($funcionario);
});

test('o hemocentro não altera nem exclui funcionário de outra unidade', function (string $metodo, string $rota) {
    $hemocentro = Hemocentro::factory()->create();
    $outroHemocentro = Hemocentro::factory()->create();
    $funcionario = Funcionario::factory()->paraHemocentro($outroHemocentro)->create([
        'cpf_fun' => '52998224725',
        'nome_fun' => 'Nome Original',
    ]);

    $this->actingAs($hemocentro, 'hemocentro')
        ->{$metodo}(route($rota, $funcionario), [
            'nome_funcionario' => 'Nome Alterado',
            'tipo_funcionario' => CargoFuncionario::Responsavel->value,
        ])
        ->assertNotFound();

    expect($funcionario->refresh()->nome_fun)->toBe('Nome Original');
})->with([
    'edição' => ['put', 'hemocentro.funcionarios.update'],
    'exclusão' => ['delete', 'hemocentro.funcionarios.destroy'],
]);

test('o responsável não altera nem exclui funcionário de outro hemocentro', function (string $metodo, string $rota) {
    $hemocentro = Hemocentro::factory()->create();
    $outroHemocentro = Hemocentro::factory()->create();
    $responsavel = Funcionario::factory()->paraHemocentro($hemocentro)->create([
        'cpf_fun' => '28001238938',
        'tipo_fun' => CargoFuncionario::Responsavel->value,
    ]);
    $funcionario = Funcionario::factory()->paraHemocentro($outroHemocentro)->create([
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
    'edição' => ['put', 'hemocentro.funcionarios.update'],
    'exclusão' => ['delete', 'hemocentro.funcionarios.destroy'],
]);

test('o funcionário administrativo recebe 403 na listagem e no cadastro do hemocentro', function (string $metodo, string $rota) {
    $hemocentro = Hemocentro::factory()->create();
    $administrativo = Funcionario::factory()->paraHemocentro($hemocentro)->create([
        'cpf_fun' => '52998224725',
        'tipo_fun' => CargoFuncionario::Administrativo->value,
    ]);

    $this->actingAs($administrativo, 'funcionario')
        ->{$metodo}(route($rota))
        ->assertForbidden();
})->with([
    'listagem' => ['get', 'hemocentro.funcionarios'],
    'cadastro' => ['post', 'hemocentro.funcionarios.store'],
]);

test('o funcionário administrativo recebe 403 na edição e na exclusão do hemocentro', function (string $metodo, string $rota) {
    $hemocentro = Hemocentro::factory()->create();
    $administrativo = Funcionario::factory()->paraHemocentro($hemocentro)->create([
        'cpf_fun' => '52998224725',
        'tipo_fun' => CargoFuncionario::Administrativo->value,
    ]);
    $outro = Funcionario::factory()->paraHemocentro($hemocentro)->create([
        'cpf_fun' => '12345678909',
    ]);

    $this->actingAs($administrativo, 'funcionario')
        ->{$metodo}(route($rota, $outro))
        ->assertForbidden();
})->with([
    'edição' => ['put', 'hemocentro.funcionarios.update'],
    'exclusão' => ['delete', 'hemocentro.funcionarios.destroy'],
]);

test('os aliases antigos continuam apontando para a nova gestão do hemocentro', function () {
    $hemocentro = Hemocentro::factory()->create();

    $this->actingAs($hemocentro, 'hemocentro')
        ->get(route('hemocentro.cadastro-funcionario'))
        ->assertOk()
        ->assertViewIs('hemocentro.Hem_funcionarios');

    $this->post(route('hemocentro.funcionario.store'), [
        'nome_funcionario' => 'João da Silva',
        'cpf_funcionario' => '52998224725',
        'senha_funcionario' => 'senha-segura',
        'tipo_funcionario' => CargoFuncionario::Administrativo->value,
    ])->assertRedirectToRoute('hemocentro.funcionarios');

    expect(Funcionario::where('cpf_fun', '52998224725')->count())->toBe(1);
});

test('rejeita dados inválidos na edição de funcionário do hemocentro', function (array $overrides, string $campo) {
    $hemocentro = Hemocentro::factory()->create();
    $funcionario = Funcionario::factory()->paraHemocentro($hemocentro)->create([
        'cpf_fun' => '52998224725',
    ]);

    $this->actingAs($hemocentro, 'hemocentro')
        ->put(route('hemocentro.funcionarios.update', $funcionario), array_merge([
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
