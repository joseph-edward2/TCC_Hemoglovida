<?php

namespace App\Enums;

enum UrgenciaPedido: string
{
    case Rotina = 'rotina';
    case Urgente = 'urgente';
    case Critica = 'critica';

    public function label(): string
    {
        return match ($this) {
            self::Rotina => 'Rotina',
            self::Urgente => 'Urgente',
            self::Critica => 'Crítica',
        };
    }
}
