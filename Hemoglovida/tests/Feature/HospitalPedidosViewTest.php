<?php

use App\Models\Hospital;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('mostra os oito tipos sanguíneos e os status dos pedidos na tela do hospital', function () {
    $hospital = Hospital::factory()->create();

    $response = $this->actingAs($hospital, 'hospital')
        ->get(route('hospital.pedidos'))
        ->assertOk();

    foreach (['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'] as $tipo) {
        $response->assertSee('class="blood-select__btn">'.$tipo.'</button>', false);
    }

    $response->assertSeeText('Recebida')
        ->assertSeeText('Em análise')
        ->assertSeeText('Em separação')
        ->assertSeeText('Enviado')
        ->assertSeeText('Entregue')
        ->assertDontSeeText('enviada');
});

test('a tela deixou de usar resumo, tabela e hemocentros fixos', function () {
    $hospital = Hospital::factory()->create();

    $response = $this->actingAs($hospital, 'hospital')
        ->get(route('hospital.pedidos'))
        ->assertOk();

    $response->assertSee('csrf-token', false)
        ->assertSee('id="resumoPedidos"', false)
        ->assertSee('id="listaPedidos"', false)
        ->assertSee('id="listaHemocentros"', false)
        ->assertSee('id="hemocentroBusca"', false)
        ->assertSee('id="buscaHemocentro"', false)
        ->assertSee('aria-expanded="false"', false)
        ->assertSee('role="listbox" hidden', false)
        ->assertSee('id="msg"', false)
        ->assertSee('id="msgMonitor"', false)
        ->assertSee('id="paginacaoMonitor"', false)
        ->assertSee('id="btnAtualizar"', false)
        ->assertSee('id="atualizadoEm"', false)
        ->assertSee('id="overlayChegada"', false)
        ->assertSee('id="conferiBolsas"', false)
        ->assertSee('id="btnConfirmarRecebimento"', false)
        ->assertSee('js/hospital/pedidos.js', false);

    foreach (['>12<', '>05<', '>08<', '>03<', '2 km', '5 km', 'api.exemplo.com', 'em tempo real'] as $mock) {
        $response->assertDontSee($mock, false);
    }
});

test('os arquivos da tela de pedidos não contêm mocks nem urls externas', function () {
    $blade = (string) file_get_contents(resource_path('views/hospital/Hospital_pedidos.blade.php'));
    $js = (string) file_get_contents(public_path('js/hospital/pedidos.js'));
    $css = (string) file_get_contents(public_path('css/hospital/pedidos.css'));

    expect($blade.$js.$css)->not->toContain('api.exemplo.com')
        ->not->toContain('Hemocentro Central (2km)')
        ->not->toContain('Hemocentro Leste (5km)')
        ->not->toContain('setInterval')
        ->not->toContain('innerHTML')
        ->not->toContain("criarElemento('td', '', criarElemento(")
        ->toContain('renderizarPaginacao')
        ->toContain('buscaHemocentroEl')
        ->toContain('navegarHemocentros')
        ->toContain('selecionarHemocentro')
        ->toContain('hemo-option__meta');
});
