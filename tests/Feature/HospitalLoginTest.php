<?php

use App\Models\Hospital;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('o hospital entra com CNPJ e senha e encerra a sessão', function () {
    $hospital = Hospital::factory()->create(['cnpj_hosp' => '11222333000144', 'senha_hosp' => 'senha-do-hospital']);
    $this->post(route('hospital.login.store'), ['login' => '11.222.333/0001-44', 'password' => 'incorreta'])->assertSessionHasErrors('login');
    $this->post(route('hospital.login.store'), ['login' => '11.222.333/0001-44', 'password' => 'senha-do-hospital'])->assertRedirectToRoute('hospital.estoque');
    $this->assertAuthenticatedAs($hospital, 'hospital');
    $this->post(route('hospital.logout'))->assertRedirectToRoute('hospital.login');
    $this->assertGuest('hospital');
});
