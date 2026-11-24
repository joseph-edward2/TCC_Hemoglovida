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
