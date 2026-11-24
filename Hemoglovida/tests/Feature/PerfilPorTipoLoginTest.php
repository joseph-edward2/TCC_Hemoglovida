<?php

use App\Enums\CargoFuncionario;
use App\Models\Funcionario;
use App\Models\Hemocentro;
use App\Models\Hospital;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('exibe a foto de pessoa e o nome do funcionário administrativo nas telas com navbar', function (string $rota) {
    $funcionario = Funcionario::factory()->create([
        'cpf_fun' => '28001238938',
        'nome_fun' => 'Funcionário de Teste',
        'tipo_fun' => CargoFuncionario::Administrativo->value,
    ]);

    $response = $this->actingAs($funcionario, 'funcionario')
        ->get(route($rota))
        ->assertOk();

    $response->assertSee('assets/avatar.jpg')
        ->assertDontSee('assets/avatar_instituicao.jpg')
        ->assertSee('Bem-vindo, Funcionário de Teste');
})->with([
    'agendamento do hemocentro' => ['hemocentro.agendamento'],
    'alertas do hemocentro' => ['hemocentro.alertas'],
    'atendimento do hemocentro' => ['hemocentro.atendimento'],
    'estoque do hemocentro' => ['hemocentro.estoque'],
    'pedidos do hemocentro' => ['hemocentro.pedidos'],
    'início do hemocentro' => ['hemocentro.inicio'],
    'triagem do hemocentro' => ['hemocentro.triagem'],
    'minha conta do hemocentro' => ['hemocentro.usuario'],
    'estoque do hospital' => ['hospital.estoque'],
    'pedidos do hospital' => ['hospital.pedidos'],
    'minha conta do hospital' => ['hospital.usuario'],
]);

test('exibe a foto de instituição e o nome do responsável nas telas com navbar', function (string $rota) {
    $hospital = Hospital::factory()->create();
    $responsavel = Funcionario::factory()->paraHospital($hospital)->create([
        'cpf_fun' => '52998224725',
        'nome_fun' => 'Renato Gonçalves',
        'tipo_fun' => CargoFuncionario::Responsavel->value,
    ]);

    $response = $this->actingAs($responsavel, 'funcionario')
        ->get(route($rota))
        ->assertOk();

    $response->assertSee('assets/avatar_instituicao.jpg')
        ->assertDontSee('assets/avatar.jpg')
        ->assertSee('Bem-vindo, Renato Gonçalves');
})->with([
    'agendamento do hemocentro' => ['hemocentro.agendamento'],
    'alertas do hemocentro' => ['hemocentro.alertas'],
    'atendimento do hemocentro' => ['hemocentro.atendimento'],
    'estoque do hemocentro' => ['hemocentro.estoque'],
    'pedidos do hemocentro' => ['hemocentro.pedidos'],
    'início do hemocentro' => ['hemocentro.inicio'],
    'triagem do hemocentro' => ['hemocentro.triagem'],
    'minha conta do hemocentro' => ['hemocentro.usuario'],
    'estoque do hospital' => ['hospital.estoque'],
    'pedidos do hospital' => ['hospital.pedidos'],
    'minha conta do hospital' => ['hospital.usuario'],
]);

test('exibe a foto de instituição e o rótulo do hemocentro nas telas com navbar', function (string $rota) {
    $hemocentro = Hemocentro::factory()->create();

    $response = $this->actingAs($hemocentro, 'hemocentro')
        ->get(route($rota))
        ->assertOk();

    $response->assertSee('assets/avatar_instituicao.jpg')
        ->assertDontSee('assets/avatar.jpg')
        ->assertSee('Bem-vindo, Hemocentro');
})->with([
    'agendamento' => ['hemocentro.agendamento'],
    'alertas' => ['hemocentro.alertas'],
    'atendimento' => ['hemocentro.atendimento'],
    'estoque' => ['hemocentro.estoque'],
    'pedidos' => ['hemocentro.pedidos'],
    'início' => ['hemocentro.inicio'],
    'triagem' => ['hemocentro.triagem'],
    'minha conta' => ['hemocentro.usuario'],
]);

test('exibe a foto de instituição e o nome do hospital nas telas com navbar', function (string $rota) {
    $hospital = Hospital::factory()->create(['nome_hosp' => 'Hospital de Teste']);

    $response = $this->actingAs($hospital, 'hospital')
        ->get(route($rota))
        ->assertOk();

    $response->assertSee('assets/avatar_instituicao.jpg')
        ->assertDontSee('assets/avatar.jpg')
        ->assertSee('Bem-vindo, Hospital de Teste');
})->with([
    'estoque' => ['hospital.estoque'],
    'pedidos' => ['hospital.pedidos'],
    'minha conta' => ['hospital.usuario'],
]);

test('oculta o botão de cadastro de funcionário do administrativo na minha conta', function (string $rota) {
    $funcionario = Funcionario::factory()->create([
        'cpf_fun' => '28001238938',
        'tipo_fun' => CargoFuncionario::Administrativo->value,
    ]);

    $this->actingAs($funcionario, 'funcionario')
        ->get(route($rota))
        ->assertOk()
        ->assertDontSeeText('Cadastro Funcionário');
})->with([
    'hemocentro' => ['hemocentro.usuario'],
    'hospital' => ['hospital.usuario'],
]);

test('mantém o e-mail editável na conta do hospital', function () {
    $funcionario = Funcionario::factory()->paraHospital(Hospital::factory()->create())->create([
        'cpf_fun' => '52998224725',
        'tipo_fun' => CargoFuncionario::Responsavel->value,
    ]);

    $this->actingAs($funcionario, 'funcionario')
        ->get(route('hospital.usuario'))
        ->assertOk()
        ->assertSee('id="emailField" value="renatao@email.com"', false)
        ->assertDontSee('id="emailField" value="renatao@email.com" disabled', false);
});

test('exibe o botão Sair da conta do hospital com o ícone do hemocentro', function () {
    $hospital = Hospital::factory()->create();

    $this->actingAs($hospital, 'hospital')
        ->get(route('hospital.usuario'))
        ->assertOk()
        ->assertSee('<button type="submit" class="btn-logout"><i class="ph ph-sign-out" aria-hidden="true"></i> Sair</button>', false);
});

test('exibe o botão de cadastro apenas na instituição do responsável', function (string $instituicao, string $rotaSemBotao, string $rotaComBotao, string $campo) {
    $responsavel = $instituicao === 'hospital'
        ? Funcionario::factory()->paraHospital(Hospital::factory()->create())->create([
            'cpf_fun' => '52998224725',
            'tipo_fun' => CargoFuncionario::Responsavel->value,
        ])
        : Funcionario::factory()->paraHemocentro(Hemocentro::factory()->create())->create([
            'cpf_fun' => '52998224725',
            'tipo_fun' => CargoFuncionario::Responsavel->value,
        ]);

    $this->actingAs($responsavel, 'funcionario')
        ->get(route($rotaSemBotao))
        ->assertOk()
        ->assertDontSeeText('Cadastro Funcionário');

    $this->get(route($rotaComBotao))
        ->assertOk()
        ->assertSeeText('Cadastro Funcionário')
        ->assertSee('href="'.route($campo).'"', false);
})->with([
    'hospital' => ['hospital', 'hemocentro.usuario', 'hospital.usuario', 'hospital.cadastro-funcionario'],
    'hemocentro' => ['hemocentro', 'hospital.usuario', 'hemocentro.usuario', 'hemocentro.cadastro-funcionario'],
]);

test('exibe o botão de cadastro de funcionário para o hemocentro na minha conta', function () {
    $hemocentro = Hemocentro::factory()->create();

    $this->actingAs($hemocentro, 'hemocentro')
        ->get(route('hemocentro.usuario'))
        ->assertOk()
        ->assertSeeText('Cadastro Funcionário')
        ->assertSee('href="'.route('hemocentro.cadastro-funcionario').'"', false);
});

test('exibe o botão de cadastro de funcionário para o hospital na minha conta', function () {
    $hospital = Hospital::factory()->create();

    $this->actingAs($hospital, 'hospital')
        ->get(route('hospital.usuario'))
        ->assertOk()
        ->assertSeeText('Cadastro Funcionário')
        ->assertSee('href="'.route('hospital.cadastro-funcionario').'"', false);
});

test('não exibe a ação de encerrar conta na minha conta do hospital', function (string $guard, callable $criarUsuario) {
    $usuario = $criarUsuario();

    $this->actingAs($usuario, $guard)
        ->get(route('hospital.usuario'))
        ->assertOk()
        ->assertDontSeeText('Encerrar Conta')
        ->assertDontSee('id="deleteAccountBtn"', false);
})->with([
    'hospital' => ['hospital', fn () => Hospital::factory()->create()],
    'funcionário' => ['funcionario', fn () => Funcionario::factory()->create(['cpf_fun' => '28001238938'])],
]);
