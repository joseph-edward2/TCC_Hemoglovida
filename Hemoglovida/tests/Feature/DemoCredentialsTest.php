<?php

use App\Models\Funcionario;
use Database\Seeders\HemocentroLoginSeeder;
use Database\Seeders\HospitalLoginSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('autentica com as credenciais de demonstração do hemocentro', function () {
    $this->seed(HemocentroLoginSeeder::class);

    $this->post(route('hemocentro.login.store'), [
        'login' => '0000001',
        'password' => 'hemocentro123',
    ])->assertRedirectToRoute('hemocentro.inicio');

    $this->assertAuthenticated('hemocentro');
});

it('autentica com as credenciais de demonstração do funcionário', function () {
    $this->seed(HemocentroLoginSeeder::class);

    $this->post(route('hemocentro.login.store'), [
        'login' => '280.012.389-38',
        'password' => 'funcionario123',
    ])->assertRedirectToRoute('hemocentro.inicio');

    $this->assertAuthenticated('funcionario');
});

it('autentica com as credenciais de demonstração do hospital', function () {
    $this->seed(HospitalLoginSeeder::class);

    $this->post(route('hospital.login.store'), [
        'login' => '11.222.333/0001-81',
        'password' => 'hospital123',
    ])->assertRedirectToRoute('hospital.estoque');

    $this->assertAuthenticated('hospital');
});

it('autentica com as credenciais de demonstração do funcionário na página do hospital', function () {
    $this->seed(HemocentroLoginSeeder::class);

    $this->post(route('hospital.login.store'), [
        'login' => '280.012.389-38',
        'password' => 'funcionario123',
    ])->assertRedirectToRoute('hospital.estoque');

    $this->assertAuthenticated('funcionario');
});

it('usa Renato Gonçalves como responsável demonstrativo do hospital', function () {
    $this->seed(HospitalLoginSeeder::class);

    $responsavel = Funcionario::where('cpf_fun', '52998224725')->firstOrFail();

    expect($responsavel->nome_fun)->toBe('Renato Gonçalves');

    $this->post(route('hospital.login.store'), [
        'login' => '529.982.247-25',
        'password' => 'funcionario123',
    ])->assertRedirectToRoute('hospital.estoque');

    $this->get(route('hospital.usuario'))
        ->assertOk()
        ->assertSee('Bem-vindo, Renato Gonçalves');
});
