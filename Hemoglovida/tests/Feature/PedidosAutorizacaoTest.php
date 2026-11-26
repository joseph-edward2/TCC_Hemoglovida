<?php

use App\Enums\CargoFuncionario;
use App\Models\Hemocentro;
use App\Models\Hospital;
use App\Models\Pedido;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-25 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('funcionário de hemocentro recebe 403 nos endpoints do hospital', function (string $rota, string $metodo) {
    $hemocentro = Hemocentro::factory()->create();
    $funcionario = $hemocentro->funcionarios()->create([
        'nome_fun' => 'Funcionário do hemocentro',
        'senha_fun' => 'senha',
        'tipo_fun' => CargoFuncionario::Administrativo->value,
    ]);
    $pedido = Pedido::factory()->create(['cnes_hem' => $hemocentro->cnes_hem]);

    $this->actingAs($funcionario, 'funcionario')
        ->withHeader('Accept', 'application/json')
        ->{$metodo}(route($rota, $pedido))
        ->assertForbidden();
})->with([
    ['hospital.pedidos.resumo', 'getJson'],
    ['hospital.pedidos.listar', 'getJson'],
    ['hospital.pedidos.hemocentros', 'getJson'],
    ['hospital.pedidos.bolsas-em-transito', 'getJson'],
    ['hospital.pedidos.confirmar-chegada', 'postJson'],
]);

test('funcionário de hemocentro não acessa as páginas do hospital', function (string $rota) {
    $hemocentro = Hemocentro::factory()->create();
    $funcionario = $hemocentro->funcionarios()->create([
        'nome_fun' => 'Funcionário do hemocentro',
        'senha_fun' => 'senha',
        'tipo_fun' => CargoFuncionario::Administrativo->value,
    ]);

    $this->actingAs($funcionario, 'funcionario')
        ->get(route($rota))
        ->assertForbidden();
})->with([
    'estoque' => 'hospital.estoque',
    'pedidos' => 'hospital.pedidos',
    'minha conta' => 'hospital.usuario',
    'cadastro de funcionário' => 'hospital.cadastro-funcionario',
]);

test('sem sessão os endpoints retornam 401 em json', function (string $rota, string $metodo) {
    $pedido = Pedido::factory()->create();

    $this->withHeader('Accept', 'application/json')
        ->{$metodo}(route($rota, $pedido))
        ->assertUnauthorized();
})->with([
    ['hospital.pedidos.resumo', 'getJson'],
    ['hospital.pedidos.listar', 'getJson'],
    ['hospital.pedidos.hemocentros', 'getJson'],
    ['hospital.pedidos.bolsas-em-transito', 'getJson'],
    ['hospital.pedidos.confirmar-chegada', 'postJson'],
]);

test('responsável e administrativo do hospital podem consultar e criar pedidos', function (string $cargo) {
    $hospital = Hospital::factory()->create();
    $funcionario = $hospital->funcionarios()->create([
        'nome_fun' => 'Funcionário do hospital',
        'senha_fun' => 'senha',
        'tipo_fun' => $cargo,
    ]);
    $hemocentro = Hemocentro::factory()->create();

    $this->actingAs($funcionario, 'funcionario')
        ->withHeader('Accept', 'application/json')
        ->getJson(route('hospital.pedidos.listar'))
        ->assertOk();

    $this->actingAs($funcionario, 'funcionario')
        ->withHeader('Accept', 'application/json')
        ->post(route('hospital.pedidos.store'), [
            'tipo_sanguineo' => 'AB+',
            'quantidade' => 1,
            'urgencia' => 'rotina',
            'paciente_setor' => 'Paciente / UTI',
            'cnes_hem' => $hemocentro->cnes_hem,
            'requisicao' => UploadedFile::fake()->createWithContent('req.pdf', '%PDF-1.4'),
        ])
        ->assertCreated();
})->with([CargoFuncionario::Responsavel->value, CargoFuncionario::Administrativo->value]);

test('pedidos de outro hospital retornam 404 em todas as rotas com binding', function (string $rota, string $metodo) {
    $hospital = Hospital::factory()->create();
    $outroHospital = Hospital::factory()->create();
    $pedido = Pedido::factory()->noHospital($outroHospital)->create();

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->{$metodo}(route($rota, $pedido))
        ->assertNotFound();
})->with([
    ['hospital.pedidos.requisicao', 'get'],
    ['hospital.pedidos.bolsas-em-transito', 'getJson'],
    ['hospital.pedidos.confirmar-chegada', 'postJson'],
]);
