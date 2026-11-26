<?php

namespace App;

use Illuminate\Database\Query\Builder;
use stdClass;

class ResumoPedidos
{
    public const FUSO = 'America/Sao_Paulo';

    public const TIPOS_SANGUINEOS = ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'];

    public const ETAPAS = [
        'pendente' => 'Recebido',
        'em_separacao' => 'Em Separação',
        'enviado' => 'Enviado',
        'entregue' => 'Entregue',
    ];

    public const STATUS = self::ETAPAS + ['recusado' => 'Recusado'];

    public const FILA_CRITICA = "COALESCE(urgencia_ped, '') = 'critica' AND status_ped IN ('pendente', 'em_separacao', 'enviado')";

    public static function totais(Builder $pedidos, bool $recebimentos = false): stdClass
    {
        $hoje = now(self::FUSO)->toImmutable()->startOfDay();

        $contagemDiaria = $recebimentos
            ? "COUNT(CASE WHEN status_ped = 'entregue' AND data_hora_recebimento_ped >= ? AND data_hora_recebimento_ped < ? THEN 1 END) AS recebidos_hoje"
            : 'COUNT(CASE WHEN data_hora_envio_ped >= ? AND data_hora_envio_ped < ? THEN 1 END) AS enviados_hoje';

        return (clone $pedidos)
            ->selectRaw("COUNT(CASE WHEN status_ped = 'pendente' THEN 1 END) AS pendentes")
            ->selectRaw("COUNT(CASE WHEN status_ped = 'em_separacao' THEN 1 END) AS em_separacao")
            ->selectRaw($contagemDiaria, [
                $hoje->utc(), $hoje->addDay()->utc(),
            ])
            ->selectRaw('COUNT(CASE WHEN '.self::FILA_CRITICA.' THEN 1 END) AS criticos')
            ->first();
    }
}
