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
}
