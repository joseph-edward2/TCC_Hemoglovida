<?php

use App\Enums\CargoFuncionario;
use App\Models\Funcionario;
use App\Models\Hemocentro;
use App\Models\Hospital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('mostra o link de cadastro de funcionário na conta do hemocentro', function () {
    $hemocentro = Hemocentro::factory()->create();

    $this->actingAs($hemocentro, 'hemocentro')
        ->get(route('hemocentro.usuario'))
        ->assertOk()
        ->assertSeeText('Cadastro Funcionário')
        ->assertSee('href="'.url('/hemocentro/Hem_cadastro_funcionario.blade.php').'"', false);
});

test('exibe a tela de cadastro de funcionário do hemocentro para o hemocentro autenticado', function () {
    $hemocentro = Hemocentro::factory()->create();

    $this->actingAs($hemocentro, 'hemocentro')
        ->get(route('hemocentro.cadastro-funcionario'))
        ->assertOk()
        ->assertSeeText('Cadastro de Funcionário')
        ->assertSee('name="nome_funcionario"', false)
        ->assertSee('name="cpf_funcionario"', false)
        ->assertSee('name="senha_funcionario"', false)
        ->assertSee('name="tipo_funcionario"', false)
        ->assertSee('name="_token"', false)
        ->assertSee('action="'.route('hemocentro.funcionario.store').'"', false)
        ->assertDontSee('name="codigo_registro_funcionario"', false);
});

test('bloqueia funcionário administrativo na tela de cadastro do hemocentro', function () {
    $funcionario = Funcionario::factory()->create([
        'tipo_fun' => CargoFuncionario::Administrativo->value,
    ]);

    $this->actingAs($funcionario, 'funcionario')
        ->get(route('hemocentro.cadastro-funcionario'))
        ->assertRedirectToRoute('hemocentro.login');
});

test('exibe a tela de cadastro do hemocentro para o responsável autenticado', function () {
    $hemocentro = Hemocentro::factory()->create();
    $responsavel = Funcionario::factory()->paraHemocentro($hemocentro)->create([
        'cpf_fun' => '52998224725',
        'tipo_fun' => CargoFuncionario::Responsavel->value,
    ]);

    $this->actingAs($responsavel, 'funcionario')
        ->get(route('hemocentro.cadastro-funcionario'))
        ->assertOk()
        ->assertSeeText('Cadastro de Funcionário')
        ->assertSee('action="'.route('hemocentro.funcionario.store').'"', false);
});

test('bloqueia responsável de hospital na tela de cadastro do hemocentro', function () {
    $hospital = Hospital::factory()->create();
    $responsavel = Funcionario::factory()->paraHospital($hospital)->create([
        'cpf_fun' => '52998224725',
        'tipo_fun' => CargoFuncionario::Responsavel->value,
    ]);

    $this->actingAs($responsavel, 'funcionario')
        ->get(route('hemocentro.cadastro-funcionario'))
        ->assertRedirectToRoute('hemocentro.login');
});

test('redireciona visitantes da tela de cadastro de funcionário do hemocentro para o login', function () {
    $this->get(route('hemocentro.cadastro-funcionario'))
        ->assertRedirectToRoute('hemocentro.login');
});

test('cadastra funcionário vinculado ao hemocentro da sessão', function () {
    $hemocentro = Hemocentro::factory()->create(['cnes_hem' => '1234567']);

    $this->actingAs($hemocentro, 'hemocentro')
        ->post(route('hemocentro.funcionario.store'), [
            'nome_funcionario' => 'Maria da Silva',
            'cpf_funcionario' => '280.012.389-38',
            'senha_funcionario' => 'senha-segura',
            'tipo_funcionario' => CargoFuncionario::Administrativo->value,
        ])
        ->assertRedirectToRoute('hemocentro.cadastro-funcionario')
        ->assertSessionHas('status', 'Funcionário cadastrado com sucesso.');

    $funcionario = Funcionario::firstOrFail();

    expect($funcionario->cnes_hem)->toBe('1234567')
        ->and($funcionario->cnpj_hosp)->toBeNull()
        ->and($funcionario->cpf_fun)->toBe('28001238938')
        ->and($funcionario->nome_fun)->toBe('Maria da Silva')
        ->and($funcionario->tipo_fun)->toBe(CargoFuncionario::Administrativo)
        ->and(Hash::check('senha-segura', $funcionario->senha_fun))->toBeTrue();
});

test('responsável do hemocentro cadastra funcionário no próprio vínculo', function () {
    $hemocentro = Hemocentro::factory()->create(['cnes_hem' => '1234567']);
    $responsavel = Funcionario::factory()->paraHemocentro($hemocentro)->create([
        'cpf_fun' => '52998224725',
        'tipo_fun' => CargoFuncionario::Responsavel->value,
    ]);

    $this->actingAs($responsavel, 'funcionario')
        ->post(route('hemocentro.funcionario.store'), [
            'nome_funcionario' => 'Maria da Silva',
            'cpf_funcionario' => '28001238938',
            'senha_funcionario' => 'senha-segura',
            'tipo_funcionario' => CargoFuncionario::Administrativo->value,
            'cnes_hem' => '9999999',
        ])
        ->assertRedirectToRoute('hemocentro.cadastro-funcionario')
        ->assertSessionHas('status', 'Funcionário cadastrado com sucesso.');

    $criado = Funcionario::where('cpf_fun', '28001238938')->firstOrFail();

    expect($criado->cnes_hem)->toBe('1234567')
        ->and($criado->cnpj_hosp)->toBeNull();
});

test('ignora a instituição enviada no payload e usa somente a sessão', function () {
    $hemocentro = Hemocentro::factory()->create(['cnes_hem' => '1234567']);
    $hospital = Hospital::factory()->create();

    $this->actingAs($hemocentro, 'hemocentro')
        ->post(route('hemocentro.funcionario.store'), [
            'nome_funcionario' => 'Maria da Silva',
            'cpf_funcionario' => '28001238938',
            'senha_funcionario' => 'senha-segura',
            'tipo_funcionario' => CargoFuncionario::Administrativo->value,
            'cnes_hem' => '9999999',
            'cnpj_hosp' => $hospital->cnpj_hosp,
            'codigo_registro_funcionario' => '9999999',
        ])
        ->assertRedirectToRoute('hemocentro.cadastro-funcionario');

    $funcionario = Funcionario::firstOrFail();

    expect($funcionario->cnes_hem)->toBe('1234567')
        ->and($funcionario->cnpj_hosp)->toBeNull();
});

test('rejeita CPF inválido no cadastro do hemocentro', function () {
    $hemocentro = Hemocentro::factory()->create();

    $this->actingAs($hemocentro, 'hemocentro')
        ->post(route('hemocentro.funcionario.store'), [
            'nome_funcionario' => 'Maria da Silva',
            'cpf_funcionario' => '28001238939',
            'senha_funcionario' => 'senha-segura',
            'tipo_funcionario' => CargoFuncionario::Administrativo->value,
        ])
        ->assertSessionHasErrors('cpf_funcionario');

    expect(Funcionario::count())->toBe(0);
});

test('rejeita CPF já cadastrado no hemocentro', function () {
    $hemocentro = Hemocentro::factory()->create();
    Funcionario::factory()->paraHemocentro($hemocentro)->create(['cpf_fun' => '28001238938']);

    $this->actingAs($hemocentro, 'hemocentro')
        ->post(route('hemocentro.funcionario.store'), [
            'nome_funcionario' => 'Maria da Silva',
            'cpf_funcionario' => '280.012.389-38',
            'senha_funcionario' => 'senha-segura',
            'tipo_funcionario' => CargoFuncionario::Administrativo->value,
        ])
        ->assertSessionHasErrors(['cpf_funcionario' => 'Já existe um funcionário com este CPF.']);

    expect(Funcionario::count())->toBe(1);
});

test('bloqueia o POST de cadastro do hemocentro para funcionário administrativo', function () {
    $funcionario = Funcionario::factory()->create([
        'tipo_fun' => CargoFuncionario::Administrativo->value,
    ]);

    $this->actingAs($funcionario, 'funcionario')
        ->post(route('hemocentro.funcionario.store'), [
            'nome_funcionario' => 'Maria da Silva',
            'cpf_funcionario' => '28001238938',
            'senha_funcionario' => 'senha-segura',
            'tipo_funcionario' => CargoFuncionario::Administrativo->value,
        ])
        ->assertRedirectToRoute('hemocentro.login');

    expect(Funcionario::count())->toBe(1);
});

test('bloqueia o POST de cadastro do hemocentro para responsável de hospital', function () {
    $hospital = Hospital::factory()->create();
    $responsavel = Funcionario::factory()->paraHospital($hospital)->create([
        'cpf_fun' => '52998224725',
        'tipo_fun' => CargoFuncionario::Responsavel->value,
    ]);

    $this->actingAs($responsavel, 'funcionario')
        ->post(route('hemocentro.funcionario.store'), [
            'nome_funcionario' => 'Maria da Silva',
            'cpf_funcionario' => '28001238938',
            'senha_funcionario' => 'senha-segura',
            'tipo_funcionario' => CargoFuncionario::Administrativo->value,
        ])
        ->assertRedirectToRoute('hemocentro.login');

    expect(Funcionario::count())->toBe(1);
});

test('limita a 20 cadastros por minuto no hemocentro', function () {
    $hemocentro = Hemocentro::factory()->create();

    $this->actingAs($hemocentro, 'hemocentro');

    $payload = [
        'nome_funcionario' => 'Maria da Silva',
        'cpf_funcionario' => '11111111111',
        'senha_funcionario' => 'senha-segura',
        'tipo_funcionario' => CargoFuncionario::Administrativo->value,
    ];

    for ($requisicao = 0; $requisicao < 20; $requisicao++) {
        $this->postJson(route('hemocentro.funcionario.store'), $payload)->assertStatus(422);
    }

    $this->postJson(route('hemocentro.funcionario.store'), $payload)->assertStatus(429);
});
