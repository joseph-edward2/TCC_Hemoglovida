<?php

use App\Models\Funcionario;
use App\Models\Hemocentro;
use App\Models\Hospital;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('autentica um hospital com CNPJ formatado e senha do banco', function () {
    $hospital = Hospital::factory()->create([
        'cnpj_hosp' => '11222333000181',
        'senha_hosp' => 'hospital123',
    ]);

    $response = $this->post(route('hospital.login.store'), [
        'login' => '11.222.333/0001-81',
        'password' => 'hospital123',
    ]);

    $response->assertRedirectToRoute('hospital.estoque');
    $this->assertAuthenticatedAs($hospital, 'hospital');
});

it('autentica um hospital com CNPJ sem máscara e senha do banco', function () {
    $hospital = Hospital::factory()->create([
        'cnpj_hosp' => '11222333000181',
        'senha_hosp' => 'hospital123',
    ]);

    $response = $this->post(route('hospital.login.store'), [
        'login' => '11222333000181',
        'password' => 'hospital123',
    ]);

    $response->assertRedirectToRoute('hospital.estoque');
    $this->assertAuthenticatedAs($hospital, 'hospital');
});

it('autentica um funcionário do hospital com CPF na página do hospital', function () {
    $hospital = Hospital::factory()->create();
    $funcionario = Funcionario::factory()->paraHospital($hospital)->create([
        'cpf_fun' => '28001238938',
        'senha_fun' => 'funcionario123',
    ]);

    $response = $this->post(route('hospital.login.store'), [
        'login' => '280.012.389-38',
        'password' => 'funcionario123',
    ]);

    $response->assertRedirectToRoute('hospital.estoque');
    $this->assertAuthenticatedAs($funcionario, 'funcionario');
    $this->assertGuest('hospital');
});

it('recusa funcionário de hemocentro na página do hospital', function () {
    Funcionario::factory()->create([
        'cnes_hem' => Hemocentro::factory(),
        'cpf_fun' => '28001238938',
        'senha_fun' => 'funcionario123',
    ]);

    $this->from(route('hospital.login'))
        ->post(route('hospital.login.store'), [
            'login' => '280.012.389-38',
            'password' => 'funcionario123',
        ])
        ->assertRedirectToRoute('hospital.login')
        ->assertSessionHasErrors(['login' => 'Este CPF não está vinculado a um hospital.']);

    $this->assertGuest('funcionario');
    $this->assertGuest('hospital');
});

it('não aceita CNES como login do hospital', function () {
    Hospital::factory()->create([
        'cnes_hosp' => '2222222',
        'senha_hosp' => 'hospital123',
    ]);

    $this->postJson(route('hospital.login.store'), [
        'login' => '2222222',
        'password' => 'hospital123',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.login.0', 'Informe um CNPJ de 14 dígitos ou um CPF válido.');

    $this->assertGuest('hospital');
});

it('mostra senha inválida para um CNPJ cadastrado sem devolver a senha na sessão', function () {
    Hospital::factory()->create([
        'cnpj_hosp' => '11222333000181',
        'senha_hosp' => 'hospital123',
    ]);

    $this->from(route('hospital.login'))
        ->post(route('hospital.login.store'), [
            'login' => '11.222.333/0001-81',
            'password' => 'senha-incorreta',
        ])
        ->assertRedirectToRoute('hospital.login')
        ->assertSessionHasErrors(['password' => 'Senha inválida'])
        ->assertSessionMissing('_old_input.password');

    $this->assertGuest('hospital');
});

it('mostra senha inválida para um CPF cadastrado', function () {
    Funcionario::factory()->create([
        'cnes_hem' => Hemocentro::factory(),
        'cpf_fun' => '28001238938',
        'senha_fun' => 'funcionario123',
    ]);

    $this->from(route('hospital.login'))
        ->post(route('hospital.login.store'), [
            'login' => '280.012.389-38',
            'password' => 'senha-incorreta',
        ])
        ->assertRedirectToRoute('hospital.login')
        ->assertSessionHasErrors(['password' => 'Senha inválida']);

    $this->assertGuest('funcionario');
});

it('recusa documentos inválidos no servidor', function (string $login) {
    $this->postJson(route('hospital.login.store'), [
        'login' => $login,
        'password' => 'hospital123',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.login.0', 'Informe um CNPJ de 14 dígitos ou um CPF válido.');
})->with([
    'CNPJ com dígito verificador inválido' => '11222333000182',
    'CNES' => '0000000',
    'CPF com dígito verificador inválido' => '28001238939',
    'formato inválido' => '123456',
]);

it('redireciona visitantes da área do hospital para o login', function () {
    $this->get(route('hospital.estoque'))
        ->assertRedirectToRoute('hospital.login');
});

it('permite que um funcionário do hospital autenticado acesse a área do hospital', function () {
    $hospital = Hospital::factory()->create();
    $funcionario = Funcionario::factory()->paraHospital($hospital)->create([
        'cpf_fun' => '28001238938',
    ]);

    $this->actingAs($funcionario, 'funcionario')
        ->get(route('hospital.pedidos'))
        ->assertOk();
});

it('limita tentativas repetidas de login', function () {
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $this->postJson(route('hospital.login.store'), [
            'login' => '11222333000182',
            'password' => 'hospital123',
        ])->assertUnprocessable();
    }

    $this->postJson(route('hospital.login.store'), [
        'login' => '11222333000182',
        'password' => 'hospital123',
    ])->assertTooManyRequests();
});

it('exibe o nome do hospital logado no painel', function () {
    $hospital = Hospital::factory()->create([
        'cnpj_hosp' => '11222333000181',
        'nome_hosp' => 'Hospital de Teste',
    ]);

    $this->actingAs($hospital, 'hospital')
        ->get(route('hospital.usuario'))
        ->assertOk()
        ->assertSeeText('Hospital de Teste')
        ->assertSee(route('hospital.logout'));
});

it('exibe o nome do funcionário do hospital logado no painel do hospital', function () {
    $hospital = Hospital::factory()->create();
    $funcionario = Funcionario::factory()->paraHospital($hospital)->create([
        'cpf_fun' => '28001238938',
        'nome_fun' => 'Funcionário de Teste',
    ]);

    $this->actingAs($funcionario, 'funcionario')
        ->get(route('hospital.usuario'))
        ->assertOk()
        ->assertSeeText('Funcionário de Teste');
});

it('encerra a sessão do hospital ao sair', function () {
    $hospital = Hospital::factory()->create(['cnpj_hosp' => '11222333000181']);

    $this->actingAs($hospital, 'hospital')
        ->post(route('hospital.logout'))
        ->assertRedirectToRoute('hospital.login');

    $this->assertGuest('hospital');
});

it('encerra também a sessão do funcionário ao sair do hospital', function () {
    $funcionario = Funcionario::factory()->create([
        'cnes_hem' => Hemocentro::factory(),
        'cpf_fun' => '28001238938',
    ]);

    $this->actingAs($funcionario, 'funcionario')
        ->post(route('hospital.logout'))
        ->assertRedirectToRoute('hospital.login');

    $this->assertGuest('funcionario');
    $this->assertGuest('hospital');
});
