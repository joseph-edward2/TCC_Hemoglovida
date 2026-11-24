<?php

namespace App\Enums;

enum TipoSanguineo: string
{
    case OMenos = 'O-';
    case OMais = 'O+';
    case AMenos = 'A-';
    case AMais = 'A+';
    case BMenos = 'B-';
    case BMais = 'B+';
    case ABMenos = 'AB-';
    case ABMais = 'AB+';

    public function label(): string
    {
        return match ($this) {
            self::OMenos => 'O negativo',
            self::OMais => 'O positivo',
            self::AMenos => 'A negativo',
            self::AMais => 'A positivo',
            self::BMenos => 'B negativo',
            self::BMais => 'B positivo',
            self::ABMenos => 'AB negativo',
            self::ABMais => 'AB positivo',
        };
    }
}
