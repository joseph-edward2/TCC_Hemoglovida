<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HemocentroDashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): View
    {
        $cnes = $request->user()->cnes_hem;
        $consultedAt = now('America/Sao_Paulo')->toImmutable();
        $today = $consultedAt->startOfDay();
        $publishedEvents = DB::table('evento')->where('cnes_hem', $cnes)->where('status_evento', 'publicado');
        $stockByType = DB::table('bolsa_sangue')
            ->where('cnes_hem', $cnes)
            ->selectRaw('UPPER(TRIM(tipo_bolsa)) AS blood_type, SUM(quant_bolsa) AS total')
            ->groupBy('blood_type')
            ->pluck('total', 'blood_type');

        return view('hemocentro.Hem_Tela_Inicial', [
            'consultedAt' => $consultedAt,
            'stockTotal' => $stockByType->sum(),
            'stockByType' => $stockByType,
            'appointmentsToday' => DB::table('agendamento')
                ->where('cnes_hem', $cnes)
                ->where('status_agendamento', '<>', 'cancelado')
                ->where('data_hora_agendamento', '>=', $today->utc())
                ->where('data_hora_agendamento', '<', $today->addDay()->utc())
                ->count(),
            'upcomingEvents' => (clone $publishedEvents)
                ->where('data_hora_evento', '>=', $consultedAt->utc())
                ->count(),
            'pendingRequests' => DB::table('pedido')
                ->where('cnes_hem', $cnes)
                ->where('status_ped', 'pendente')
                ->count(),
            'events' => $publishedEvents
                ->orderByDesc('data_hora_evento')
                ->orderByDesc('id_evento')
                ->limit(4)
                ->get(['titulo_evento', 'tipo_evento', 'data_hora_evento', 'local_evento', 'desc_evento']),
            'recentRequests' => DB::table('pedido')
                ->join('hospital', 'hospital.cnpj_hosp', '=', 'pedido.cnpj_hosp')
                ->where('pedido.cnes_hem', $cnes)
                ->orderByDesc('pedido.data_hora_ped')
                ->orderByDesc('pedido.id_ped')
                ->limit(3)
                ->get(['hospital.nome_hosp', 'pedido.desc_ped', 'pedido.quant_ped', 'pedido.data_hora_ped', 'pedido.status_ped']),
            'dailyDonations' => $this->dailyDonations($cnes, $consultedAt),
        ]);
    }

    /**
     * @return list<array{day: CarbonImmutable, count: int}>
     */
    private function dailyDonations(string $cnes, CarbonImmutable $consultedAt): array
    {
        $today = $consultedAt->startOfDay();
        $firstDay = $today->subDays(6);
        $timezone = $consultedAt->getTimezone();
        $counts = DB::table('agendamento')
            ->where('cnes_hem', $cnes)
            ->where('data_hora_coleta', '>=', $firstDay->utc())
            ->where('data_hora_coleta', '<=', $consultedAt->utc())
            ->pluck('data_hora_coleta')
            ->countBy(fn (string $collectedAt): string => CarbonImmutable::parse($collectedAt, 'UTC')->setTimezone($timezone)->toDateString());

        $days = [];
        for ($day = $firstDay; $day <= $today; $day = $day->addDay()) {
            $days[] = ['day' => $day->locale('pt_BR'), 'count' => $counts->get($day->toDateString(), 0)];
        }

        return $days;
    }
}
