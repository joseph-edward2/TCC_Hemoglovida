<?php

namespace App\Enums;

enum StatusPedido: string
{
    case Pendente = 'pendente';
    case EmAnalise = 'em_analise';
    case EmSeparacao = 'em_separacao';
    case Enviado = 'enviado';
    case Entregue = 'entregue';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Recebida',
            self::EmAnalise => 'Em análise',
            self::EmSeparacao => 'Em separação',
            self::Enviado => 'Enviado',
            self::Entregue => 'Entregue',
        };
    }

    /**
     * Etapa correspondente na timeline fixa de cinco posições.
     */
    public function etapa(): int
    {
        return match ($this) {
            self::Pendente => 1,
            self::EmAnalise => 2,
            self::EmSeparacao => 3,
            self::Enviado => 4,
            self::Entregue => 5,
        };
    }
}
