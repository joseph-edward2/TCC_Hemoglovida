<?php

namespace App\Services;

use App\Enums\StatusBolsa;
use App\Enums\TipoSanguineo;
use App\Models\BolsaSangue;
use App\Models\Hemocentro;
use App\Models\Hospital;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EstoqueHospitalService
{
    /**
     * @return array<string, mixed>
     */
    public function resumo(Hospital $hospital): array
    {
        $quantidades = BolsaSangue::query()
            ->doHospital($hospital->cnpj_hosp)
            ->disponiveis()
            ->where('data_venc_bolsa', '>=', today()->toDateString())
            ->selectRaw('COUNT(*) AS quantidade, tipo_bolsa')
            ->groupBy('tipo_bolsa')
            ->pluck('quantidade', 'tipo_bolsa');

        $aVencer = BolsaSangue::query()
            ->doHospital($hospital->cnpj_hosp)
            ->disponiveis()
            ->aVencer()
            ->selectRaw('COUNT(*) AS quantidade, tipo_bolsa')
            ->groupBy('tipo_bolsa')
            ->pluck('quantidade', 'tipo_bolsa');

        $limiteCritico = (int) config(
            'estoque.limite_critico_percentual',
            config('estoque.critico_percentual', 25),
        );

        $cards = [];

        foreach (TipoSanguineo::cases() as $tipo) {
            $quantidade = (int) ($quantidades->get($tipo->value) ?? 0);
            $meta = (int) (config('estoque.metas.'.$tipo->value) ?? 0);
            $percentual = $meta > 0 ? (int) round($quantidade / $meta * 100) : 0;

            $cards[] = [
                'tipo_bolsa' => $tipo->value,
                'quantidade' => $quantidade,
                'meta' => $meta,
                'percentual' => $percentual,
                'critico' => $percentual < $limiteCritico,
                'a_vencer' => (int) ($aVencer->get($tipo->value) ?? 0),
            ];
        }

        $tiposCriticos = array_column(array_filter(
            $cards,
            fn (array $card): bool => $card['critico'],
        ), 'tipo_bolsa');

        return [
            'hospital' => ['nome' => $hospital->nome_hosp],
            'atualizado_em' => now()->toISOString(),
            'cards' => $cards,
            'total_criticos' => count($tiposCriticos),
            'tipos_criticos' => array_values($tiposCriticos),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function consumo(Hospital $hospital): array
    {
        $semanas = $this->semanas();

        $expressoesEntradas = [];
        $bindingsEntradas = [];

        foreach ($semanas as $indice => $semana) {
            $expressoesEntradas[] = sprintf(
                'SUM(CASE WHEN data_hora_recebimento_bolsa BETWEEN ? AND ? THEN 1 ELSE 0 END) AS entradas_%d',
                $indice,
            );
            $bindingsEntradas[] = $semana['inicio']->toDateTimeString();
            $bindingsEntradas[] = $semana['fim']->format('Y-m-d H:i:s.u');
        }

        $linhaEntradas = BolsaSangue::query()
            ->doHospital($hospital->cnpj_hosp)
            ->selectRaw(implode(', ', $expressoesEntradas), $bindingsEntradas)
            ->first();

        $expressoesSaidas = [];
        $bindingsSaidas = [];

        foreach ($semanas as $indice => $semana) {
            foreach ([StatusBolsa::Utilizada, StatusBolsa::Descartada] as $status) {
                $alias = $status === StatusBolsa::Utilizada ? 'utilizadas' : 'descartadas';
                $expressoesSaidas[] = sprintf(
                    'SUM(CASE WHEN disponibilidade_bolsa = ? AND data_hora_saida_bolsa BETWEEN ? AND ? THEN 1 ELSE 0 END) AS %s_%d',
                    $alias,
                    $indice,
                );
                $bindingsSaidas[] = $status->value;
                $bindingsSaidas[] = $semana['inicio']->toDateTimeString();
                $bindingsSaidas[] = $semana['fim']->format('Y-m-d H:i:s.u');
            }
        }

        $linhaSaidas = BolsaSangue::query()
            ->doHospital($hospital->cnpj_hosp)
            ->selectRaw(implode(', ', $expressoesSaidas), $bindingsSaidas)
            ->first();

        $resultado = [];

        foreach ($semanas as $indice => $semana) {
            $utilizadas = (int) ($linhaSaidas->{'utilizadas_'.$indice} ?? 0);
            $descartadas = (int) ($linhaSaidas->{'descartadas_'.$indice} ?? 0);
            $entradas = (int) ($linhaEntradas->{'entradas_'.$indice} ?? 0);

            $resultado[] = [
                'rotulo' => $semana['rotulo'],
                'entradas' => $entradas,
                'saidas' => $utilizadas + $descartadas,
                'utilizadas' => $utilizadas,
                'descartadas' => $descartadas,
            ];
        }

        $maiorValor = collect($resultado)->max(
            fn (array $semana): int => max($semana['entradas'], $semana['saidas']),
        );

        return [
            'periodo' => [
                'inicio' => $semanas->first()['inicio']->toISOString(),
                'fim' => $semanas->last()['fim']->toISOString(),
            ],
            'semanas' => $resultado,
            'escala_max' => max(20, (int) (ceil($maiorValor / 20) * 20)),
        ];
    }

    public function bolsasQuery(Hospital $hospital): Builder
    {
        return BolsaSangue::query()
            ->doHospital($hospital->cnpj_hosp)
            ->with('hemocentro')
            ->orderByRaw('CASE WHEN disponibilidade_bolsa = ? THEN 0 ELSE 1 END', [StatusBolsa::EmTransito->value])
            ->orderByDesc('data_hora_recebimento_bolsa')
            ->orderBy('isbt');
    }

    public static function origem(BolsaSangue $bolsa): string
    {
        $rotulo = $bolsa->hemocentro?->rotulo;

        if ($rotulo !== null) {
            return $rotulo;
        }

        return Hemocentro::rotuloParaCnes((string) $bolsa->cnes_hem);
    }

    public static function formatarIsbt(string $isbt): string
    {
        if (strlen($isbt) !== 13) {
            return $isbt;
        }

        return substr($isbt, 0, 5).' '.substr($isbt, 5, 2).' '.substr($isbt, 7, 6);
    }

    /**
     * @return Collection<int, array{rotulo: string, inicio: Carbon, fim: Carbon}>
     */
    private function semanas(): Collection
    {
        return collect(range(0, 3))->map(function (int $indice): array {
            $inicio = today()->subDays(27 - (7 * $indice))->startOfDay();

            return [
                'rotulo' => 'SEMANA '.($indice + 1),
                'inicio' => $inicio,
                'fim' => $inicio->copy()->addDays(6)->endOfDay(),
            ];
        });
    }
}
