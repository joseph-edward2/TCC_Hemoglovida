<?php

use App\Models\Funcionario;
use App\Models\Hemocentro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('mostra o estoque por tipo sanguíneo apenas do hemocentro autenticado', function (string $guard) {
    $this->travelTo(Carbon::parse('2026-11-24 01:30:00', 'UTC'));

    $hemocentro = Hemocentro::factory()->create();
    $otherHemocentro = Hemocentro::factory()->create();
    $funcionario = Funcionario::factory()->create(['cnes_hem' => $hemocentro->cnes_hem]);

    $bag = ['cnes_hem' => $hemocentro->cnes_hem, 'tipo_bolsa' => 'O+', 'disponibilidade_bolsa' => 'disponivel'];
    DB::table('bolsa_sangue')->insert([
        $bag + ['isbt' => '00000000000000000001', 'quant_bolsa' => 40, 'data_venc_bolsa' => '2026-11-23', 'data_hora_coleta' => '2026-11-24 01:00:00'],
        ['tipo_bolsa' => ' o+ ', 'isbt' => '00000000000000000002', 'quant_bolsa' => 62, 'data_venc_bolsa' => '2026-11-30', 'data_hora_coleta' => '2026-10-25 03:00:00'] + $bag,
        $bag + ['isbt' => '00000000000000000003', 'quant_bolsa' => 3, 'data_venc_bolsa' => '2026-11-22', 'data_hora_coleta' => '2026-10-25 02:59:59'],
        $bag + ['isbt' => '00000000000000000004', 'quant_bolsa' => 8, 'data_venc_bolsa' => '2026-12-01', 'data_hora_coleta' => '2026-11-24 01:30:01'],
        ['disponibilidade_bolsa' => 'em_analise', 'isbt' => '00000000000000000005', 'quant_bolsa' => 2, 'data_venc_bolsa' => null, 'data_hora_coleta' => null] + $bag,
        ['cnes_hem' => $otherHemocentro->cnes_hem, 'isbt' => '00000000000000000006', 'quant_bolsa' => 900, 'data_venc_bolsa' => '2026-11-25', 'data_hora_coleta' => '2026-11-24 01:25:00'] + $bag,
        ['tipo_bolsa' => 'B+', 'disponibilidade_bolsa' => 'reservado', 'isbt' => '00000000000000000007', 'quant_bolsa' => 15, 'data_venc_bolsa' => '2026-12-30', 'data_hora_coleta' => '2026-11-01 03:00:00'] + $bag,
    ]);

    $this->actingAs($guard === 'hemocentro' ? $hemocentro : $funcionario, $guard)
        ->get(route('hemocentro.estoque'))
        ->assertOk()
        ->assertSeeInOrder([
            '<section class="stock-grid">',
            '<span class="blood-type">O+</span>',
            '<span class="count-value">115</span>',
            'style="width:100%"',
            '115% do',
            '102 bolsas com validade em até 7 dias',
            '<span class="blood-type">A-</span>',
            '<span class="count-value">0</span>',
            'Nenhuma expiração próxima',
            '<span class="blood-type">B+</span>',
            '<span class="count-value">15</span>',
            '20% do',
        ], false)
        ->assertSeeText('O estoque de O-, A-, A+, B-, AB-, AB+ está abaixo de 20% da meta.')
        ->assertViewHas('collections', fn (array $collections): bool => array_column($collections['periods'], 'quantity') === [62, 15, 0, 0, 40])
        ->assertSeeHtml('<span class="total-value">117</span>')
        ->assertSeeInOrder([
            '<table class="table">',
            '00000000000000000004',
            '00000000000000000001',
            '<td>40</td>',
            '<td>23/11/2026</td>',
            '<td class="date-alert">23/11/2026</td>',
            'Disponível',
            '00000000000000000007',
            'Reservado',
            '00000000000000000002',
            '00000000000000000003',
        ], false)
        ->assertDontSeeText('00000000000000000006')
        ->assertDontSeeText('00000000000000000005')
        ->assertSeeText('Mostrando 1–5 de 6 registros')
        ->assertSeeHtml('href="'.route('hemocentro.estoque', ['page' => 2]).'#inventario" rel="next"');
})->with(['hemocentro', 'funcionario']);

test('mostra registros sem data na próxima página do inventário', function () {
    $hemocentro = Hemocentro::factory()->create();
    $bag = [
        'cnes_hem' => $hemocentro->cnes_hem,
        'tipo_bolsa' => 'A+',
        'quant_bolsa' => 1,
        'data_venc_bolsa' => null,
        'disponibilidade_bolsa' => 'em_analise',
    ];

    DB::table('bolsa_sangue')->insert(array_map(
        fn (int $number): array => $bag + [
            'isbt' => "ISBT-{$number}",
            'data_hora_coleta' => $number === 1 ? null : '2026-11-01 03:00:00',
        ],
        range(1, 6),
    ));

    $this->actingAs($hemocentro, 'hemocentro')
        ->get(route('hemocentro.estoque', ['page' => 2]))
        ->assertOk()
        ->assertSeeInOrder(['ISBT-1', '<td>—</td>', '>—</td>', 'Em análise'], false)
        ->assertDontSeeText('ISBT-2')
        ->assertSeeText('Mostrando 6–6 de 6 registros')
        ->assertSeeHtml('href="'.route('hemocentro.estoque', ['page' => 1]).'#inventario" rel="prev"');
});

test('mostra inventário vazio e zero coletas quando o hemocentro não tem bolsas', function () {
    $hemocentro = Hemocentro::factory()->create();

    $this->actingAs($hemocentro, 'hemocentro')
        ->get(route('hemocentro.estoque'))
        ->assertOk()
        ->assertSeeText('Nenhuma bolsa cadastrada.')
        ->assertSeeHtml('<span class="total-value">0</span>');
});

test('redireciona visitantes do estoque para o login', function () {
    $this->get(route('hemocentro.estoque'))->assertRedirectToRoute('hemocentro.login');
});
