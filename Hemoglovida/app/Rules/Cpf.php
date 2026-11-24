<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class Cpf implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid($value)) {
            $fail('O :attribute deve ser um CPF válido.');
        }
    }

    /**
     * Verifica se o valor é um CPF válido, com ou sem formatação.
     */
    public static function isValid(mixed $value): bool
    {
        $cpf = self::normalizar($value);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }

        for ($position = 9; $position <= 10; $position++) {
            $sum = 0;

            for ($index = 0; $index < $position; $index++) {
                $sum += (int) $cpf[$index] * ($position + 1 - $index);
            }

            $remainder = $sum % 11;
            $expected = $remainder < 2 ? 0 : 11 - $remainder;

            if ((int) $cpf[$position] !== $expected) {
                return false;
            }
        }

        return true;
    }

    /**
     * Remove a formatação e devolve apenas os dígitos do CPF.
     */
    public static function normalizar(mixed $value): string
    {
        return preg_replace('/[^0-9]/', '', (string) $value) ?? '';
    }
}
