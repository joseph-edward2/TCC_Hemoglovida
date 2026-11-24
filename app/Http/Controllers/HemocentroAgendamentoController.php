<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalvarAgendaRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HemocentroAgendamentoController extends Controller
{
    private const TIMEZONE = 'America/Sao_Paulo';

    public function index(Request $request): View|JsonResponse
    {
        $request->validate(['mes' => ['sometimes', 'date_format:Y-m']]);
        $today = CarbonImmutable::now(self::TIMEZONE);
        $month = $request->has('mes')
            ? CarbonImmutable::createFromFormat('!Y-m', $request->input('mes'), self::TIMEZONE)
            : $today->startOfMonth();
        $cnes = $request->user()->cnes_hem;
        $bookings = [];

        $times = DB::table('agendamento')
            ->where('cnes_hem', $cnes)
            ->where('status_agendamento', '<>', 'cancelado')
            ->where('data_hora_agendamento', '>=', $month->utc())
            ->where('data_hora_agendamento', '<', $month->addMonth()->utc())
            ->select('data_hora_agendamento')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('data_hora_agendamento')
            ->get();

        foreach ($times as $slot) {
            $local = CarbonImmutable::parse($slot->data_hora_agendamento, 'UTC')->setTimezone(self::TIMEZONE);
            $day = $local->toDateString();
            $time = $local->format('H:i');
            $bookings[$day][$time] = ($bookings[$day][$time] ?? 0) + (int) $slot->total;
        }

        if ($request->expectsJson()) {
            return response()->json(['bookings' => $bookings]);
        }

        $center = DB::table('hemocentro')->where('cnes_hem', $cnes)->firstOrFail(['configuracao_agenda', 'versao_agenda']);

        return view('hemocentro.Hem_Agendamento', ['agenda' => [
            'date' => $today->isSameMonth($month) ? $today->toDateString() : $month->toDateString(),
            'bookings' => $bookings,
            'settings' => json_decode($center->configuracao_agenda ?? 'null', true, flags: JSON_THROW_ON_ERROR),
            'revision' => (int) $center->versao_agenda,
        ]]);
    }

    public function update(SalvarAgendaRequest $request): JsonResponse
    {
        $revision = $request->validated('revision');
        $updated = DB::table('hemocentro')
            ->where('cnes_hem', $request->user()->cnes_hem)
            ->where('versao_agenda', $revision)
            ->update([
                'configuracao_agenda' => json_encode($request->safe()->except('revision'), JSON_THROW_ON_ERROR),
                'versao_agenda' => $revision + 1,
            ]);

        abort_if($updated === 0, 409, 'A agenda foi atualizada. Recarregue a página antes de editar novamente.');

        return response()->json(['revision' => $revision + 1]);
    }
}
