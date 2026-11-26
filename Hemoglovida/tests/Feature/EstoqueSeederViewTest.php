<?php

use App\Enums\CargoFuncionario;
use App\Enums\StatusBolsa;
use App\Models\BolsaSangue;
use App\Models\Funcionario;
use App\Models\Hemocentro;
use App\Models\Hospital;
use App\Models\Pedido;
use Carbon\Carbon;
use Database\Seeders\HospitalDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-25 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('o seeder cria setenta bolsas determinísticas e remove dados legados do hospital', function () {
    $this->seed(HospitalDemoSeeder::class);

    $bolsas = BolsaSangue::where('cnpj_hosp', '11222333000181')->get();

    expect($bolsas)->toHaveCount(70)
        ->and($bolsas->pluck('isbt')->unique())->toHaveCount(70)
        ->and($bolsas->every(fn (BolsaSangue $bolsa): bool => preg_match('/^W000026\d{6}$/', $bolsa->isbt) === 1))->toBeTrue()
        ->and($bolsas->every(fn (BolsaSangue $bolsa): bool => $bolsa->quant_bolsa === 1))->toBeTrue();

    BolsaSangue::factory()->create([
        'cnpj_hosp' => '11222333000181',
        'isbt' => 'LEGADO00001',
    ]);

    $this->seed(HospitalDemoSeeder::class);

    expect(BolsaSangue::where('cnpj_hosp', '11222333000181')->count())->toBe(70)
        ->and(BolsaSangue::where('isbt', 'LEGADO00001')->exists())->toBeFalse()
        ->and(Pedido::where('cnpj_hosp', '11222333000181')->count())->toBe(12);
});

test('o seeder distribui status, vencimento, origem e bolsas de outro hospital', function () {
    $this->seed(HospitalDemoSeeder::class);

    $bolsas = BolsaSangue::where('cnpj_hosp', '11222333000181')->get();
    $vencendo = $bolsas->filter(fn (BolsaSangue $bolsa): bool => $bolsa->disponibilidade_bolsa === StatusBolsa::Disponivel
        && $bolsa->data_venc_bolsa->between(today(), today()->addDays(7)));

    expect($bolsas->where('disponibilidade_bolsa', StatusBolsa::EmTransito)->count())->toBe(6)
        ->and($bolsas->where('disponibilidade_bolsa', StatusBolsa::Reservada)->count())->toBe(8)
        ->and($bolsas->where('disponibilidade_bolsa', StatusBolsa::Utilizada)->count())->toBe(6)
        ->and($bolsas->where('disponibilidade_bolsa', StatusBolsa::Descartada)->count())->toBe(4)
        ->and($bolsas->filter(fn (BolsaSangue $bolsa): bool => $bolsa->vencida)->count())->toBe(5)
        ->and($vencendo)->toHaveCount(6)
        ->and($bolsas->pluck('cnes_hem')->unique()->sort()->values()->all())->toBe(['0000001', '0000002'])
        ->and(Hemocentro::where('cnes_hem', '0000001')->value('nome_hem'))->toBe('Hemocentro Central')
        ->and(Hemocentro::where('cnes_hem', '0000002')->value('nome_hem'))->toBe('Hemocentro Leste')
        ->and(BolsaSangue::where('cnpj_hosp', '98765432000198')->count())->toBe(3);
});

test('o resumo semeado conta bolsas e mostra tipos críticos, intermediários e saudáveis', function () {
    $this->seed(HospitalDemoSeeder::class);

    $hospital = Hospital::findOrFail('11222333000181');

    $resposta = $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->get(route('hospital.estoque.resumo'))
        ->assertOk();

    $cards = collect($resposta->json('cards'))->keyBy('tipo_bolsa');

    expect($cards->sum('quantidade'))->toBe(41)
        ->and($cards->get('O-')['quantidade'])->toBe(15)
        ->and($cards->get('A-')['quantidade'])->toBe(10)
        ->and($cards->get('AB+')['quantidade'])->toBe(13)
        ->and($cards->get('B-')['critico'])->toBeTrue()
        ->and($cards->get('AB-')['critico'])->toBeTrue()
        ->and($cards->get('B+')['critico'])->toBeTrue()
        ->and($cards->get('A-')['critico'])->toBeFalse()
        ->and($cards->get('AB+')['critico'])->toBeFalse()
        ->and($cards->get('O-')['percentual'])->toBe(50)
        ->and($cards->get('AB-')['a_vencer'])->toBe(1)
        ->and(BolsaSangue::where('cnpj_hosp', '11222333000181')->count())->toBe(70);
});

test('a tela de estoque mantém contêineres dinâmicos e a sessão do hospital', function () {
    $hospital = Hospital::factory()->create(['nome_hosp' => 'Hospital de Teste']);

    $resposta = $this->actingAs($hospital, 'hospital')
        ->get(route('hospital.estoque'))
        ->assertOk();

    $resposta->assertSee('Estoque do Hospital — Hospital de Teste')
        ->assertSee('csrf-token', false)
        ->assertSee('id="estoqueTitulo"', false)
        ->assertSee('id="estoqueAtualizado"', false)
        ->assertSee('id="estoqueAlerta"', false)
        ->assertSee('id="estoqueCards"', false)
        ->assertSee('id="estoqueGrafico"', false)
        ->assertSee('id="estoqueTabela"', false)
        ->assertSee('id="estoquePaginacao"', false)
        ->assertSee('id="estoqueMensagem"', false)
        ->assertSee('id="selectBolsa"', false)
        ->assertSee('id="statusBolsa"', false)
        ->assertSee('id="pacienteDestino"', false)
        ->assertSee('id="infoDataEnvio"', false)
        ->assertSee('id="infoAboRh"', false)
        ->assertSee('id="infoDataValidade"', false)
        ->assertSee('id="infoOrigem"', false)
        ->assertSee('id="infoIDDoacao"', false)
        ->assertSee('js/hospital/estoque.js', false);
});

test('os arquivos da tela não contêm mocks nem dados de doador', function () {
    $blade = (string) file_get_contents(resource_path('views/hospital/Hospital_estoque.blade.php'));
    $estoqueJs = (string) file_get_contents(public_path('js/hospital/estoque.js'));
    $baseJs = (string) file_get_contents(public_path('js/hospital/base.js'));
    $css = (string) file_get_contents(public_path('css/hospital/estoque.css'));
    $conteudo = $blade.$estoqueJs.$baseJs;

    expect($conteudo)->not->toContain('api.exemplo.com')
        ->not->toContain('bolsasMock')
        ->not->toContain('H-1234')
        ->not->toContain('Jesse Pinkman')
        ->not->toContain('Nome do Doador')
        ->not->toContain('Caderneta do Doador')
        ->not->toContain('Data da Doação')
        ->not->toContain('80 saídas e 54 entradas');

    expect($estoqueJs)->toContain('buscarDaAPI')
        ->toContain('createElement')
        ->toContain('textContent');

    expect($baseJs)->toContain('async function buscarDaAPI')
        ->toContain('csrf-token')
        ->toContain('metodo');

    expect($css)->toContain('status-tag--em-transito')
        ->toContain('status-tag--descartado')
        ->toContain('estoque-vazio')
        ->toContain('estoque-erro');
});

test('o seeder não altera o vínculo do funcionário responsável', function () {
    $this->seed(HospitalDemoSeeder::class);

    expect(Funcionario::where('cpf_fun', '52998224725')
        ->where('cnpj_hosp', '11222333000181')
        ->where('tipo_fun', CargoFuncionario::Responsavel->value)
        ->exists())->toBeTrue();
});
