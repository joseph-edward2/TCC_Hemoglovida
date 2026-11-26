<?php

use App\Enums\StatusPedido;
use App\Models\BolsaSangue;
use App\Models\Hemocentro;
use App\Models\Hospital;
use App\Models\Pedido;
use Carbon\Carbon;
use Database\Seeders\HospitalDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-25 12:00:00');
    Storage::fake('local');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('o seeder cria doze pedidos com todos os estados e arquivos pdf', function () {
    $this->seed(HospitalDemoSeeder::class);

    $pedidos = Pedido::where('cnpj_hosp', '11222333000181')->get();

    expect($pedidos)->toHaveCount(12)
        ->and($pedidos->where('status_ped', StatusPedido::Pendente->value)->count())->toBe(2)
        ->and($pedidos->where('status_ped', StatusPedido::EmAnalise->value)->count())->toBe(1)
        ->and($pedidos->where('status_ped', StatusPedido::EmSeparacao->value)->count())->toBe(2)
        ->and($pedidos->where('status_ped', StatusPedido::Enviado->value)->count())->toBe(3)
        ->and($pedidos->where('status_ped', StatusPedido::Entregue->value)->count())->toBe(4)
        ->and($pedidos->filter(fn (Pedido $pedido): bool => $pedido->status_ped === StatusPedido::Entregue
            && $pedido->data_hora_entrega_ped?->isToday())->count())->toBe(3)
        ->and($pedidos->filter(fn (Pedido $pedido): bool => $pedido->urgencia_ped->value === 'critica'
            && $pedido->status_ped !== StatusPedido::Entregue)->count())->toBe(2)
        ->and($pedidos->every(fn (Pedido $pedido): bool => str_starts_with($pedido->desc_ped, '[DEMO-')))->toBeTrue()
        ->and($pedidos->every(fn (Pedido $pedido): bool => filled($pedido->url_requisicao_transfusao_ped)
            && Storage::disk('local')->exists($pedido->url_requisicao_transfusao_ped)))->toBeTrue()
        ->and(Pedido::where('cnpj_hosp', '98765432000198')->count())->toBeGreaterThan(0);
});

test('o seeder vincula bolsas em trânsito aos pedidos enviados sem duplicar o inventário', function () {
    $this->seed(HospitalDemoSeeder::class);

    $hospital = Hospital::findOrFail('11222333000181');
    $enviados = Pedido::doHospital($hospital->cnpj_hosp)
        ->where('status_ped', StatusPedido::Enviado->value)->get();

    expect(BolsaSangue::doHospital($hospital->cnpj_hosp)->count())->toBe(70)
        ->and($enviados->filter(fn (Pedido $pedido): bool => $pedido->bolsas()->emTransito()->count() >= 2)->count())->toBe(2)
        ->and($enviados->filter(fn (Pedido $pedido): bool => $pedido->bolsas()->emTransito()->count() === 1)->count())->toBe(1)
        ->and($enviados->filter(fn (Pedido $pedido): bool => $pedido->bolsas()
            ->whereNotNull('data_hora_recebimento_bolsa')->exists())->count())->toBe(1)
        ->and(BolsaSangue::whereNull('cnpj_hosp')->disponiveis()->count())->toBeGreaterThan(10)
        ->and(Hemocentro::where('cnes_hem', '0000001')->first()->rotulo)->toBe('Hemocentro Central')
        ->and(Hemocentro::where('cnes_hem', '0000002')->first()->rotulo)->toBe('Hemocentro Leste');
});

test('o seeder pode ser executado duas vezes sem duplicar dados', function () {
    $this->seed(HospitalDemoSeeder::class);
    $this->seed(HospitalDemoSeeder::class);

    expect(Pedido::where('cnpj_hosp', '11222333000181')->count())->toBe(12)
        ->and(BolsaSangue::where('cnpj_hosp', '11222333000181')->count())->toBe(70)
        ->and(BolsaSangue::whereNull('cnpj_hosp')->count())->toBe(20);
});

test('o comando simula o envio do hemocentro e deixa a chegada pronta para confirmar', function () {
    $this->seed(HospitalDemoSeeder::class);

    $pedido = Pedido::doHospital('11222333000181')
        ->where('status_ped', StatusPedido::Pendente->value)->firstOrFail();
    $pedido->update(['quant_ped' => 2, 'tipo_sanguineo_ped' => 'O-', 'cnes_hem' => '0000001']);

    expect(Artisan::call('hospital:simular-envio', [
        'id_ped' => $pedido->id_ped,
        '--bolsas' => 2,
    ]))->toBe(0);

    $pedido->refresh();
    $bolsas = $pedido->bolsas()->emTransito()->get();

    expect($pedido->status_ped)->toBe(StatusPedido::Enviado)
        ->and($pedido->data_hora_envio_ped)->not->toBeNull()
        ->and($bolsas)->toHaveCount(2)
        ->and($bolsas->every(fn (BolsaSangue $bolsa): bool => $bolsa->cnpj_hosp === '11222333000181'
            && $bolsa->data_hora_envio_bolsa !== null))->toBeTrue();
});

test('o comando avisa quando não há bolsas suficientes no hemocentro', function () {
    $this->seed(HospitalDemoSeeder::class);

    $pedido = Pedido::doHospital('11222333000181')
        ->where('status_ped', StatusPedido::Pendente->value)->firstOrFail();

    expect(Artisan::call('hospital:simular-envio', [
        'id_ped' => $pedido->id_ped,
        '--bolsas' => 21,
    ]))->toBe(1)
        ->and(Artisan::output())->toContain('Não há bolsas suficientes');
});

test('o comando se recusa a rodar em produção', function () {
    $this->seed(HospitalDemoSeeder::class);
    $pedido = Pedido::doHospital('11222333000181')->firstOrFail();

    $this->app->detectEnvironment(fn (): string => 'production');

    expect(Artisan::call('hospital:simular-envio', ['id_ped' => $pedido->id_ped]))->toBe(1)
        ->and(Artisan::output())->toContain('fora de produção');
});
