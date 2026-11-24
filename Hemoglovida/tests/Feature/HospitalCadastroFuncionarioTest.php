<?php

use App\Enums\CargoFuncionario;
use App\Models\Funcionario;
use App\Models\Hemocentro;
use App\Models\Hospital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('exibe a tela de cadastro de funcionário do hospital para o hospital autenticado', function () {
    $hospital = Hospital::factory()->create();

    $this->actingAs($hospital, 'hospital')
        ->get(route('hospital.cadastro-funcionario'))
        ->assertOk()
        ->assertSeeText('Cadastro de Funcionário')
        ->assertSee('name="nome_funcionario"', false)
        ->assertSee('name="cpf_funcionario"', false)
        ->assertSee('name="senha_funcionario"', false)
        ->assertSee('name="tipo_funcionario"', false)
        ->assertSee('name="_token"', false)
        ->assertSee('action="'.route('hospital.funcionario.store').'"', false)
        ->assertDontSee('name="codigo_registro_funcionario"', false);
});

test('bloqueia funcionário administrativo na tela de cadastro do hospital', function () {
    $funcionario = Funcionario::factory()->create([
        'tipo_fun' => CargoFuncionario::Administrativo->value,
    ]);

    $this->actingAs($funcionario, 'funcionario')
        ->get(route('hospital.cadastro-funcionario'))
        ->assertRedirectToRoute('hospital.login');
});

test('exibe a tela de cadastro do hospital para o responsável autenticado', function () {
    $hospital = Hospital::factory()->create();
    $responsavel = Funcionario::factory()->paraHospital($hospital)->create([
        'cpf_fun' => '28001238938',
        'tipo_fun' => CargoFuncionario::Responsavel->value,
    ]);

    $this->actingAs($responsavel, 'funcionario')
        ->get(route('hospital.cadastro-funcionario'))
        ->assertOk()
        ->assertSeeText('Cadastro de Funcionário')
        ->assertSee('action="'.route('hospital.funcionario.store').'"', false);
});

test('bloqueia responsável de hemocentro na tela de cadastro do hospital', function () {
    $hemocentro = Hemocentro::factory()->create();
    $responsavel = Funcionario::factory()->paraHemocentro($hemocentro)->create([
        'cpf_fun' => '28001238938',
        'tipo_fun' => CargoFuncionario::Responsavel->value,
    ]);

    $this->actingAs($responsavel, 'funcionario')
        ->get(route('hospital.cadastro-funcionario'))
        ->assertRedirectToRoute('hospital.login');
});

test('redireciona visitantes da tela de cadastro de funcionário do hospital para o login', function () {
    $this->get(route('hospital.cadastro-funcionario'))
        ->assertRedirectToRoute('hospital.login');
});

test('cadastra funcionário vinculado ao hospital da sessão', function () {
    $hospital = Hospital::factory()->create(['cnpj_hosp' => '11222333000181']);

    $this->actingAs($hospital, 'hospital')
        ->post(route('hospital.funcionario.store'), [
            'nome_funcionario' => 'João da Silva',
            'cpf_funcionario' => '529.982.247-25',
            'senha_funcionario' => 'senha-segura',
            'tipo_funcionario' => CargoFuncionario::Responsavel->value,
        ])
        ->assertRedirectToRoute('hospital.cadastro-funcionario')
        ->assertSessionHas('status', 'Funcionário cadastrado com sucesso.');

    $funcionario = Funcionario::firstOrFail();

    expect($funcionario->cnpj_hosp)->toBe('11222333000181')
        ->and($funcionario->cnes_hem)->toBeNull()
        ->and($funcionario->cpf_fun)->toBe('52998224725')
        ->and($funcionario->nome_fun)->toBe('João da Silva')
        ->and($funcionario->tipo_fun)->toBe(CargoFuncionario::Responsavel)
        ->and(Hash::check('senha-segura', $funcionario->senha_fun))->toBeTrue();
});

test('responsável do hospital cadastra funcionário no próprio vínculo', function () {
    $hospital = Hospital::factory()->create(['cnpj_hosp' => '11222333000181']);
    $responsavel = Funcionario::factory()->paraHospital($hospital)->create([
        'cpf_fun' => '28001238938',
        'nome_fun' => 'Renato Gonçalves',
        'tipo_fun' => CargoFuncionario::Responsavel->value,
    ]);

    $this->actingAs($responsavel, 'funcionario')
        ->post(route('hospital.funcionario.store'), [
            'nome_funcionario' => 'João da Silva',
            'cpf_funcionario' => '52998224725',
            'senha_funcionario' => 'senha-segura',
            'tipo_funcionario' => CargoFuncionario::Administrativo->value,
            'cnpj_hosp' => '99999999999999',
        ])
        ->assertRedirectToRoute('hospital.cadastro-funcionario')
        ->assertSessionHas('status', 'Funcionário cadastrado com sucesso.');

    $criado = Funcionario::where('cpf_fun', '52998224725')->firstOrFail();

    expect($criado->cnpj_hosp)->toBe('11222333000181')
        ->and($criado->cnes_hem)->toBeNull();
});

test('ignora a instituição enviada no payload e usa somente a sessão do hospital', function () {
    $hospital = Hospital::factory()->create(['cnpj_hosp' => '11222333000181']);
    $hemocentro = Hemocentro::factory()->create();

    $this->actingAs($hospital, 'hospital')
        ->post(route('hospital.funcionario.store'), [
            'nome_funcionario' => 'João da Silva',
            'cpf_funcionario' => '52998224725',
            'senha_funcionario' => 'senha-segura',
            'tipo_funcionario' => CargoFuncionario::Responsavel->value,
            'cnes_hem' => $hemocentro->cnes_hem,
            'cnpj_hosp' => '99999999999999',
        ])
        ->assertRedirectToRoute('hospital.cadastro-funcionario');

    $funcionario = Funcionario::firstOrFail();

    expect($funcionario->cnpj_hosp)->toBe('11222333000181')
        ->and($funcionario->cnes_hem)->toBeNull();
});

test('rejeita dados inválidos no cadastro do hospital', function (array $overrides, string $campo) {
    $hospital = Hospital::factory()->create();

    $this->actingAs($hospital, 'hospital')
        ->post(route('hospital.funcionario.store'), array_merge([
            'nome_funcionario' => 'João da Silva',
            'cpf_funcionario' => '52998224725',
            'senha_funcionario' => 'senha-segura',
            'tipo_funcionario' => CargoFuncionario::Responsavel->value,
        ], $overrides))
        ->assertSessionHasErrors($campo);

    expect(Funcionario::count())->toBe(0);
})->with([
    'CPF inválido' => [['cpf_funcionario' => '52998224726'], 'cpf_funcionario'],
    'senha curta' => [['senha_funcionario' => '123'], 'senha_funcionario'],
    'cargo inexistente' => [['tipo_funcionario' => 'Inexistente'], 'tipo_funcionario'],
    'sem nome' => [['nome_funcionario' => ''], 'nome_funcionario'],
]);

test('bloqueia o POST de cadastro do hospital para funcionário administrativo', function () {
    $funcionario = Funcionario::factory()->create([
        'tipo_fun' => CargoFuncionario::Administrativo->value,
    ]);

    $this->actingAs($funcionario, 'funcionario')
        ->post(route('hospital.funcionario.store'), [
            'nome_funcionario' => 'João da Silva',
            'cpf_funcionario' => '52998224725',
            'senha_funcionario' => 'senha-segura',
            'tipo_funcionario' => CargoFuncionario::Responsavel->value,
        ])
        ->assertRedirectToRoute('hospital.login');

    expect(Funcionario::count())->toBe(1);
});

test('bloqueia o POST de cadastro do hospital para responsável de hemocentro', function () {
    $hemocentro = Hemocentro::factory()->create();
    $responsavel = Funcionario::factory()->paraHemocentro($hemocentro)->create([
        'cpf_fun' => '28001238938',
        'tipo_fun' => CargoFuncionario::Responsavel->value,
    ]);

    $this->actingAs($responsavel, 'funcionario')
        ->post(route('hospital.funcionario.store'), [
            'nome_funcionario' => 'João da Silva',
            'cpf_funcionario' => '52998224725',
            'senha_funcionario' => 'senha-segura',
            'tipo_funcionario' => CargoFuncionario::Responsavel->value,
        ])
        ->assertRedirectToRoute('hospital.login');

    expect(Funcionario::count())->toBe(1);
});

test('limita a 20 cadastros por minuto no hospital', function () {
    $hospital = Hospital::factory()->create();

    $this->actingAs($hospital, 'hospital');

    $payload = [
        'nome_funcionario' => 'João da Silva',
        'cpf_funcionario' => '11111111111',
        'senha_funcionario' => 'senha-segura',
        'tipo_funcionario' => CargoFuncionario::Responsavel->value,
    ];

    for ($requisicao = 0; $requisicao < 20; $requisicao++) {
        $this->postJson(route('hospital.funcionario.store'), $payload)->assertStatus(422);
    }

    $this->postJson(route('hospital.funcionario.store'), $payload)->assertStatus(429);
});
