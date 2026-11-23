<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use stdClass;

class HemocentroEstoqueController extends Controller
{
    private const STOCK_TARGETS = [
        'O-' => 80,
        'O+' => 100,
        'A-' => 100,
        'A+' => 100,
        'B-' => 50,
        'B+' => 75,
        'AB-' => 30,
        'AB+' => 40,
    ];

    private const CRITICAL_STOCK_PERCENTAGE = 20;

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): View
    {
        $consultedAt = now('America/Sao_Paulo')->toImmutable();
        $bags = DB::table('bolsa_sangue')->where('cnes_hem', $request->user()->cnes_hem);
        $stockByType = (clone $bags)
            ->selectRaw('UPPER(TRIM(tipo_bolsa)) AS blood_type, SUM(quant_bolsa) AS total')
            ->selectRaw('SUM(CASE WHEN data_venc_bolsa BETWEEN ? AND ? THEN quant_bolsa ELSE 0 END) AS expiring', [
                $consultedAt->toDateString(),
                $consultedAt->addDays(7)->toDateString(),
            ])
            ->groupBy('blood_type')
            ->get()
            ->keyBy('blood_type');

        $stockCards = [];
        $criticalBloodTypes = [];

        foreach (self::STOCK_TARGETS as $bloodType => $target) {
            $stock = $stockByType->get($bloodType);
            $quantity = (int) ($stock->total ?? 0);
            $isCritical = $quantity * 100 < $target * self::CRITICAL_STOCK_PERCENTAGE;

            if ($isCritical) {
                $criticalBloodTypes[] = $bloodType;
            }

            $stockCards[$bloodType] = [
                'quantity' => $quantity,
                'target' => $target,
                'percentage' => (int) round($quantity * 100 / $target),
                'isCritical' => $isCritical,
                'expiring' => (int) ($stock->expiring ?? 0),
            ];
        }

        return view('hemocentro.Hem_Estoque', [
            'stockCards' => $stockCards,
            'criticalBloodTypes' => $criticalBloodTypes,
            'criticalStockPercentage' => self::CRITICAL_STOCK_PERCENTAGE,
            'inventory' => $this->inventory(clone $bags, $consultedAt),
            'collections' => $this->collections($bags, $consultedAt),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, array{isbt: string, bloodType: string, quantity: int, collectedOn: ?string, expiresOn: ?string, expiryAlert: bool, status: string, reserved: bool}>
     */
    private function inventory(Builder $bags, CarbonImmutable $consultedAt): LengthAwarePaginator
    {
        $expiryLimit = $consultedAt->addDays(7)->toDateString();

        return $bags
            ->orderByRaw('CASE WHEN data_hora_coleta IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('data_hora_coleta')
            ->orderBy('isbt')
            ->paginate(5, ['isbt', 'tipo_bolsa', 'quant_bolsa', 'data_hora_coleta', 'data_venc_bolsa', 'disponibilidade_bolsa'])
            ->fragment('inventario')
            ->through(function (stdClass $bag) use ($consultedAt, $expiryLimit): array {
                $availability = Str::lower(trim(str_replace('_', ' ', $bag->disponibilidade_bolsa)));

                return [
                    'isbt' => $bag->isbt,
                    'bloodType' => strtoupper(trim($bag->tipo_bolsa)),
                    'quantity' => (int) $bag->quant_bolsa,
                    'collectedOn' => $bag->data_hora_coleta === null
                        ? null
                        : CarbonImmutable::parse($bag->data_hora_coleta, 'UTC')->setTimezone($consultedAt->getTimezone())->format('d/m/Y'),
                    'expiresOn' => $bag->data_venc_bolsa === null
                        ? null
                        : CarbonImmutable::parse($bag->data_venc_bolsa)->format('d/m/Y'),
                    'expiryAlert' => $bag->data_venc_bolsa !== null && $bag->data_venc_bolsa <= $expiryLimit,
                    'status' => match ($availability) {
                        'disponivel' => 'Disponível',
                        'em analise' => 'Em análise',
                        default => Str::ucfirst($availability),
                    },
                    'reserved' => $availability === 'reservado',
                ];
            });
    }

    /**
     * @return array{periods: list<array{start: CarbonImmutable, end: CarbonImmutable, quantity: int}>, total: int, max: int}
     */
    private function collections(Builder $bags, CarbonImmutable $consultedAt): array
    {
        $today = $consultedAt->startOfDay();
        $firstDay = $today->subDays(29);
        $end = $today->addDay();
        $bags->whereBetween('data_hora_coleta', [$firstDay->utc(), $consultedAt->utc()]);

        $periods = [];
        for ($start = $firstDay; $start < $end; $start = $nextStart) {
            $nextStart = $start->addWeek()->min($end);
            $index = count($periods);
            $periods[] = ['start' => $start, 'end' => $nextStart->subDay()];

            $bags->selectRaw(
                "SUM(CASE WHEN data_hora_coleta >= ? AND data_hora_coleta < ? THEN quant_bolsa ELSE 0 END) AS period_{$index}",
                [$start->utc(), $nextStart->utc()],
            );
        }

        $totals = $bags->first();
        $total = 0;
        $max = 1;

        foreach ($periods as $index => $period) {
            $quantity = (int) ($totals->{"period_{$index}"} ?? 0);
            $periods[$index] = $period + ['quantity' => $quantity];
            $total += $quantity;
            $max = max($max, $quantity);
        }

        return ['periods' => $periods, 'total' => $total, 'max' => $max];
    }
}
