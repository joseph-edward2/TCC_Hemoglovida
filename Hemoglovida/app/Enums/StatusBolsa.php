<?php

namespace App\Enums;

enum StatusBolsa: string
{
    case EmTransito = 'em_transito';
    case Disponivel = 'disponivel';
    case Reservada = 'reservada';
    case Utilizada = 'utilizada';
    case Descartada = 'descartada';

    public function label(): string
    {
        return match ($this) {
            self::EmTransito => 'Em trânsito',
            self::Disponivel => 'Disponível',
            self::Reservada => 'Reservada',
            self::Utilizada => 'Utilizada',
            self::Descartada => 'Descartada',
        };
    }
}
