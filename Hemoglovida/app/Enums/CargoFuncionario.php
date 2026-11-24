<?php

namespace App\Enums;

enum CargoFuncionario: string
{
    case Responsavel = 'Responsavel';
    case Administrativo = 'Administrativo';

    public function label(): string
    {
        return match ($this) {
            self::Responsavel => 'Responsável',
            self::Administrativo => 'Administrativo',
        };
    }
}
