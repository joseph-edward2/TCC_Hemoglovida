<?php

use App\Rules\Cpf;

function mensagemDeErroCpf(string $cpf): ?string
{
    $mensagem = null;

    (new Cpf)->validate('cpf', $cpf, function ($falha) use (&$mensagem): void {
        $mensagem = (string) $falha;
    });

    return $mensagem;
}

it('aceita CPF válido com ou sem máscara', function (string $cpf) {
    expect(mensagemDeErroCpf($cpf))->toBeNull();
})->with(['sem máscara' => ['28001238938'], 'com máscara' => ['280.012.389-38']]);

it('rejeita CPFs inválidos', function (string $cpf) {
    expect(mensagemDeErroCpf($cpf))->toBe('O :attribute deve ser um CPF válido.');
})->with([
    'dígito verificador errado' => ['28001238939'],
    'dígitos repetidos' => ['11111111111'],
    'tamanho errado' => ['123'],
    'não numérico' => ['abcdefghijk'],
]);

it('normaliza a máscara antes de validar', function () {
    expect(Cpf::normalizar('280.012.389-38'))->toBe('28001238938')
        ->and(Cpf::normalizar('abc'))->toBe('');
});

it('expõe a validação booleana usada pelo login', function (string $cpf, bool $esperado) {
    expect(Cpf::isValid($cpf))->toBe($esperado);
})->with([
    'válido sem máscara' => ['28001238938', true],
    'válido com máscara' => ['280.012.389-38', true],
    'dígito verificador errado' => ['28001238939', false],
    'dígitos repetidos' => ['11111111111', false],
]);
