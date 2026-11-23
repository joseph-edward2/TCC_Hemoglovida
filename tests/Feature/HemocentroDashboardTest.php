<?php

use App\Models\Funcionario;
use App\Models\Hemocentro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('mostra os indicadores apenas do hemocentro autenticado', function (string $guard) {
    $this->travelTo(Carbon::parse('2026-11-24 01:30:00', 'UTC'));

    $hemocentro = Hemocentro::factory()->create();
    $otherHemocentro = Hemocentro::factory()->create();
    $funcionario = Funcionario::factory()->create(['cnes_hem' => $hemocentro->cnes_hem]);
    $otherFuncionario = Funcionario::factory()->create(['cnes_hem' => $otherHemocentro->cnes_hem]);

    $bag = [
        'tipo_bolsa' => 'O+',
        'disponibilidade_bolsa' => 'disponivel',
        'data_venc_bolsa' => '2026-12-31',
    ];
    DB::table('bolsa_sangue')->insert([
        $bag + ['isbt' => '00000000000000000001', 'cnes_hem' => $hemocentro->cnes_hem, 'quant_bolsa' => 30],
        ['tipo_bolsa' => ' o+ ', 'isbt' => '00000000000000000002', 'cnes_hem' => $hemocentro->cnes_hem, 'quant_bolsa' => 12] + $bag,
        $bag + ['isbt' => '00000000000000000003', 'cnes_hem' => $otherHemocentro->cnes_hem, 'quant_bolsa' => 100],
    ]);

    $event = ['local_evento' => 'Sala de coleta'];
    DB::table('evento')->insert([
        $event + ['id_evento' => 1, 'cnes_hem' => $hemocentro->cnes_hem, 'tipo_evento' => 'Campanha anterior', 'data_hora_evento' => '2026-11-23 10:00:00'],
        $event + ['id_evento' => 2, 'cnes_hem' => $hemocentro->cnes_hem, 'tipo_evento' => 'Campanha programada', 'data_hora_evento' => '2026-11-24 02:00:00'],
        $event + ['id_evento' => 3, 'cnes_hem' => $otherHemocentro->cnes_hem, 'tipo_evento' => 'Campanha de outro hemocentro', 'data_hora_evento' => '2026-11-24 03:00:00'],
    ]);

    DB::table('doador')->insert([
        'cpf_doador' => '00000000001',
        'nome_doador' => 'Doador de teste',
        'email_doador' => 'doador@example.test',
        'senha_doador' => 'hash-de-teste',
        'fone_doador' => '11900000000',
        'tipo_sanguineo' => 'O+',
        'data_nasc_doador' => '1990-01-01',
    ]);

    $appointment = ['cpf_doador' => '00000000001', 'cnes_hem' => $hemocentro->cnes_hem, 'id_evento' => 1];
    DB::table('agendamento')->insert([
        $appointment + ['data_hora_agendamento' => '2026-11-17 02:59:59', 'data_hora_coleta' => '2026-11-17 02:59:59', 'status_agendamento' => 'agendado'],
        $appointment + ['data_hora_agendamento' => '2026-11-23 02:59:59', 'data_hora_coleta' => '2026-11-23 02:59:59', 'status_agendamento' => 'agendado'],
        $appointment + ['data_hora_agendamento' => '2026-11-23 03:00:00', 'data_hora_coleta' => '2026-11-23 03:00:00', 'status_agendamento' => 'agendado'],
        $appointment + ['data_hora_agendamento' => '2026-11-24 01:00:00', 'data_hora_coleta' => '2026-11-24 01:10:00', 'status_agendamento' => 'agendado'],
        $appointment + ['data_hora_agendamento' => '2026-11-24 02:00:00', 'data_hora_coleta' => null, 'status_agendamento' => 'cancelado'],
        $appointment + ['data_hora_agendamento' => '2026-11-24 03:00:00', 'data_hora_coleta' => null, 'status_agendamento' => 'agendado'],
        [
            'cpf_doador' => '00000000001',
            'cnes_hem' => $otherHemocentro->cnes_hem,
            'id_evento' => 3,
            'data_hora_agendamento' => '2026-11-24 00:00:00',
            'data_hora_coleta' => '2026-11-24 01:00:00',
            'status_agendamento' => 'agendado',
        ],
    ]);

    DB::table('hospital')->insert([
        'cnpj_hosp' => '00000000000001',
        'cnes_hosp' => '0000001',
        'nome_hosp' => 'Hospital de teste',
        'contato_hosp' => '11900000000',
        'endereco_hosp' => 'Rua de teste',
        'senha_hosp' => 'hash-de-teste',
    ]);

    $order = ['cnpj_hosp' => '00000000000001', 'quant_ped' => 5];
    DB::table('pedido')->insert([
        $order + ['cnes_hem' => $hemocentro->cnes_hem, 'cod_reg_fun' => $funcionario->cod_reg_fun, 'status_ped' => 'pendente', 'desc_ped' => 'Pedido mais recente', 'data_hora_ped' => '2026-11-24 00:00:00'],
        $order + ['cnes_hem' => $hemocentro->cnes_hem, 'cod_reg_fun' => $funcionario->cod_reg_fun, 'status_ped' => 'enviado', 'desc_ped' => 'Pedido anterior', 'data_hora_ped' => '2026-11-23 20:00:00'],
        $order + ['cnes_hem' => $otherHemocentro->cnes_hem, 'cod_reg_fun' => $otherFuncionario->cod_reg_fun, 'status_ped' => 'pendente', 'desc_ped' => 'Pedido de outro hemocentro', 'data_hora_ped' => '2026-11-24 01:00:00'],
    ]);

    $this->actingAs($guard === 'hemocentro' ? $hemocentro : $funcionario, $guard)
        ->get(route('hemocentro.inicio'))
        ->assertOk()
        ->assertSeeInOrder([
            '42 <span>bolsas</span>',
            '2 <span>agendamentos</span>',
            '1 <span>eventos</span>',
            '1 <span>solicitações</span>',
            '<span class="blood-type">O+</span>',
            '<div class="blood-count">42</div>',
            '<span class="blood-type">A+</span>',
            '<div class="blood-count">0</div>',
        ], false)
        ->assertSeeTextInOrder(['Campanha programada', '23/11/2026 23:00', 'Programado', 'Campanha anterior', 'Data passada'])
        ->assertSeeTextInOrder(['Hospital de teste', 'Pedido mais recente', '23/11/2026 21:00', 'Pendente', 'Pedido anterior', 'Enviado'])
        ->assertDontSeeText('Campanha de outro hemocentro')
        ->assertDontSeeText('Pedido de outro hemocentro')
        ->assertViewHas('dailyDonations', fn (array $days): bool => array_column($days, 'count') === [0, 0, 0, 0, 0, 1, 2])
        ->assertSeeInOrder([
            '>60</span>', '>50</span>', '>40</span>', '>30</span>', '>20</span>', '>10</span>', '>1</span>',
            'datetime="2026-11-17"', 'datetime="2026-11-18"', 'datetime="2026-11-19"', 'datetime="2026-11-20"',
            'datetime="2026-11-21"', 'datetime="2026-11-22"', 'datetime="2026-11-23"',
        ], false);
})->with(['hemocentro', 'funcionario']);

test('mostra zero nos indicadores de um hemocentro sem registros', function () {
    $hemocentro = Hemocentro::factory()->create();

    $this->actingAs($hemocentro, 'hemocentro')
        ->get(route('hemocentro.inicio'))
        ->assertOk()
        ->assertSeeInOrder([
            '0 <span>bolsas</span>',
            '0 <span>agendamentos</span>',
            '0 <span>eventos</span>',
            '0 <span>solicitações</span>',
        ], false)
        ->assertSeeText('Nenhum evento cadastrado.')
        ->assertSeeText('Nenhum pedido cadastrado.');
});
