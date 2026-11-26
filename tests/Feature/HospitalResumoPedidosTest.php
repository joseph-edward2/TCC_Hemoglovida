<?php

use App\Models\Funcionario;
use App\Models\Hospital;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);

test('mostra os totais dos pedidos apenas do hospital autenticado', function () {
    $this->travelTo(Carbon::parse('2026-11-25 12:00:00', 'UTC'));
    $pagina = route('hospital.pedidos');
    $this->get($pagina)->assertRedirectToRoute('hospital.login');

    $hospital = Hospital::factory()->create();
    $outro = Hospital::factory()->create();
    $funcionario = Funcionario::factory()->create();
    $this->actingAs($hospital, 'hospital')->get($pagina)->assertOk()
        ->assertSee(['id="statPending">0<', 'id="statSeparating">0<', 'id="statReceivedToday">0<', 'id="statCritical">0<'], false);

    foreach ([
        [$hospital, 'pendente', 'rotina', null],
        [$hospital, 'em_separacao', 'critica', null],
        [$hospital, 'entregue', 'critica', '2026-11-25 03:00:00'],
        [$hospital, 'entregue', 'critica', '2026-11-25 02:59:59'],
        [$outro, 'pendente', 'critica', null],
        [$outro, 'entregue', 'critica', '2026-11-25 09:00:00'],
    ] as [$origem, $status, $urgencia, $recebimento]) {
        DB::table('pedido')->insert([
            'cnpj_hosp' => $origem->cnpj_hosp, 'cnes_hem' => $funcionario->cnes_hem,
            'cod_reg_fun' => $funcionario->cod_reg_fun, 'desc_ped' => 'Pedido de teste', 'quant_ped' => 3,
            'status_ped' => $status, 'urgencia_ped' => $urgencia, 'data_hora_recebimento_ped' => $recebimento,
        ]);
    }

    $this->get($pagina)->assertOk()->assertSeeInOrder([
        'id="statPending">1<', 'id="statSeparating">1<', 'id="statReceivedToday">1<', 'id="statCritical">1<',
    ], false);

    $this->travelTo(Carbon::parse('2026-11-26 02:59:59', 'UTC'));
    $this->get($pagina)->assertSee('id="statReceivedToday">1<', false);
    $this->travel(1)->second();
    $this->get($pagina)->assertSee('id="statReceivedToday">0<', false);
});
