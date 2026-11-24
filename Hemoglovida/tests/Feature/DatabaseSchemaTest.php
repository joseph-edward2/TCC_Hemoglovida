<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('uses the singular schema from the official database', function () {
    expect(Schema::hasTable('hospital'))->toBeTrue()
        ->and(Schema::hasTable('hemocentro'))->toBeTrue()
        ->and(Schema::hasTable('funcionario'))->toBeTrue()
        ->and(Schema::hasTable('bolsa_sangue'))->toBeTrue()
        ->and(Schema::hasTable('notificacao'))->toBeTrue()
        ->and(Schema::hasColumns('hospital', [
            'cnpj_hosp',
            'cnes_hosp',
            'nome_hosp',
            'contato_hosp',
            'endereco_hosp',
            'senha_hosp',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('funcionario', [
            'cod_reg_fun',
            'cnes_hem',
            'cnpj_hosp',
            'nome_fun',
            'senha_fun',
            'tipo_fun',
            'cpf_fun',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('pedido', [
            'id_ped',
            'cnpj_hosp',
            'cnes_hem',
            'cod_reg_fun',
            'tipo_sanguineo_ped',
            'urgencia_ped',
            'data_hora_envio_ped',
            'data_hora_entrega_ped',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('bolsa_sangue', [
            'isbt',
            'cnes_hem',
            'cnpj_hosp',
            'id_ped',
            'data_hora_envio_bolsa',
            'data_hora_recebimento_bolsa',
            'paciente_destino_bolsa',
        ]))->toBeTrue();
});

it('does not create the old plural tables', function () {
    foreach (['hospitais', 'hemocentros', 'funcionarios', 'pedidos', 'bolsas_sangue', 'metas_estoque'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }
});
