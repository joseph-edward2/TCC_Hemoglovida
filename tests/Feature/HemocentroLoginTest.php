<?php

use App\Models\Funcionario;
use App\Models\Hemocentro;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('autentica um hemocentro com CNES e senha do banco', function () {
    $hemocentro = Hemocentro::factory()->create(['cnes_hem' => '0123456']);

    $this->post(route('hemocentro.login.store'), ['login' => '0123456', 'password' => 'test-password'])
        ->assertRedirectToRoute('hemocentro.inicio');

    $this->assertAuthenticatedAs($hemocentro, 'hemocentro');
});

test('autentica um funcionário com CPF e senha do banco', function (string $cpf) {
    $funcionario = Funcionario::factory()->create(['cpf_fun' => '28001238938']);

    $this->post(route('hemocentro.login.store'), ['login' => $cpf, 'password' => 'test-password'])
        ->assertRedirectToRoute('hemocentro.inicio');

    $this->assertAuthenticatedAs($funcionario, 'funcionario');
})->with(['com máscara' => ['280.012.389-38'], 'sem máscara' => ['28001238938']]);

test('mostra senha inválida para um CNES cadastrado sem devolver a senha na sessão', function () {
    $hemocentro = Hemocentro::factory()->create();

    $this->from(route('hemocentro.login'))
        ->post(route('hemocentro.login.store'), ['login' => $hemocentro->cnes_hem, 'password' => 'senha-incorreta'])
        ->assertRedirectToRoute('hemocentro.login')
        ->assertSessionHasErrors(['password' => 'Senha inválida'])
        ->assertSessionMissing('_old_input.password');

    $this->assertGuest('hemocentro');
    $this->withCookie(config('session.cookie'), session()->getId())
        ->get(route('hemocentro.login'))
        ->assertSee('Senha inválida');
});

test('recusa documentos inválidos no servidor', function (string $document) {
    $this->postJson(route('hemocentro.login.store'), ['login' => $document, 'password' => 'test-password'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.login.0', 'Informe um CNES de 7 dígitos ou um CPF válido.');
})->with(['CPF inválido' => ['28001238939'], 'CNES nulo' => ['0000000'], 'formato inválido' => ['123456']]);

test('redireciona visitantes da área do hemocentro para o login', function () {
    $this->get(route('hemocentro.inicio'))->assertRedirectToRoute('hemocentro.login');
});

test('limita tentativas repetidas de login', function () {
    $credentials = ['login' => '0123456', 'password' => 'senha-incorreta'];

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $this->postJson(route('hemocentro.login.store'), $credentials)
            ->assertUnprocessable()
            ->assertJsonPath('errors.login.0', 'CNES, CPF ou senha inválidos.');
    }

    $this->postJson(route('hemocentro.login.store'), $credentials)->assertTooManyRequests();
});

test('permite acesso ao painel do hemocentro com sessão de funcionário', function () {
    $funcionario = Funcionario::factory()->create(['nome_fun' => 'Funcionário de teste']);

    $this->actingAs($funcionario, 'funcionario')
        ->get(route('hemocentro.inicio'))
        ->assertOk()
        ->assertSee('Funcionário de teste')
        ->assertDontSee(route('hemocentro.logout'));
});

test('mostra a ação de sair em minha conta', function () {
    $funcionario = Funcionario::factory()->create();

    $this->actingAs($funcionario, 'funcionario')
        ->get(route('hemocentro.usuario'))
        ->assertOk()
        ->assertSee('<form method="POST" action="'.route('hemocentro.logout').'">', false)
        ->assertSee('name="_token"', false)
        ->assertSeeText('Sair')
        ->assertDontSeeText('Encerrar Conta');
});

test('encerra a sessão do funcionário ao sair', function () {
    $funcionario = Funcionario::factory()->create();

    $this->actingAs($funcionario, 'funcionario')
        ->post(route('hemocentro.logout'))
        ->assertRedirectToRoute('hemocentro.login');

    $this->assertGuest('funcionario');
});
