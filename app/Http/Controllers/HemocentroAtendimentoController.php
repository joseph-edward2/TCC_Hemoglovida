<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use stdClass;

class HemocentroAtendimentoController extends Controller
{
    private const TIMEZONE = 'America/Sao_Paulo';

    private const DEFAULT_DONATION_VOLUME_ML = 450;

    public function index(Request $request): View
    {
        $agendamentos = $this->todayAppointments($request->user()->cnes_hem)
            ->join('doador', 'doador.cpf_doador', '=', 'agendamento.cpf_doador')
            ->orderBy('data_hora_agendamento')
            ->orderBy('protocolo_agendamento')
            ->get([
                'protocolo_agendamento',
                'data_hora_agendamento',
                'data_hora_coleta',
                'status_agendamento',
                'doador.nome_doador',
                'doador.tipo_sanguineo',
                'doador.cpf_doador',
            ])
            ->map(fn (stdClass $appointment): array => [
                'protocolo' => $appointment->protocolo_agendamento,
                'nome' => $appointment->nome_doador,
                'horario' => CarbonImmutable::parse($appointment->data_hora_agendamento, 'UTC')
                    ->setTimezone(self::TIMEZONE)->format('H:i'),
                'tipo' => $appointment->tipo_sanguineo,
                'cpf' => substr($appointment->cpf_doador, 0, 3).'.***.***-'.substr($appointment->cpf_doador, -2),
                'status' => $this->appointmentStatus($appointment),
            ]);

        return view('hemocentro.Hem_Atendimento', compact('agendamentos'));
    }

    public function confirmarChegada(Request $request, string $protocolo): JsonResponse
    {
        return $this->updatePendingAppointment($request, $protocolo, 'chegou', 'A chegada não pode ser confirmada no status atual.');
    }

    public function registrarAusencia(Request $request, string $protocolo): JsonResponse
    {
        return $this->updatePendingAppointment($request, $protocolo, 'cancelado', 'A ausência não pode ser registrada no status atual.');
    }

    private function updatePendingAppointment(Request $request, string $protocolo, string $status, string $conflictMessage): JsonResponse
    {
        $appointmentQuery = $this->todayAppointments($request->user()->cnes_hem)
            ->where('protocolo_agendamento', $protocolo);

        $updated = (clone $appointmentQuery)
            ->whereIn('status_agendamento', ['agendado', 'atrasado'])
            ->whereNull('data_hora_coleta')
            ->update(['status_agendamento' => $status]);

        if ($updated === 0) {
            $appointment = $appointmentQuery->first();
            abort_if($appointment === null, 404);
            abort_unless($this->appointmentStatus($appointment) === $status, 409, $conflictMessage);
        }

        return response()->json(['status' => $status]);
    }

    public function concluir(Request $request, string $protocolo): JsonResponse
    {
        DB::transaction(function () use ($request, $protocolo): void {
            $appointmentQuery = $this->todayAppointments($request->user()->cnes_hem)
                ->where('protocolo_agendamento', $protocolo);

            $appointment = $appointmentQuery->lockForUpdate()->first();
            abort_if($appointment === null, 404);

            $status = $this->appointmentStatus($appointment);
            if ($status === 'concluido') {
                return;
            }

            abort_unless($status === 'chegou', 409, 'Confirme a chegada antes de registrar a doação.');

            $collection = [
                'data_hora_coleta' => now('UTC'),
                'status_bolsa_doacao' => $appointment->status_bolsa_doacao ?? 'coletada',
                'volume_ml_doacao' => $appointment->volume_ml_doacao ?? self::DEFAULT_DONATION_VOLUME_ML,
            ];

            $appointmentQuery->update($collection + ['status_agendamento' => 'concluido']);

            $bloodType = DB::table('doador')
                ->where('cpf_doador', $appointment->cpf_doador)
                ->value('tipo_sanguineo');

            DB::table('bolsa_sangue')->insert($collection + [
                'isbt' => 'TMP-'.Str::upper(Str::random(16)),
                'cnes_hem' => $appointment->cnes_hem,
                'protocolo_agendamento' => $protocolo,
                'quant_bolsa' => 1,
                'tipo_bolsa' => $bloodType,
                'disponibilidade_bolsa' => 'em_analise',
            ]);
        }, 3);

        return response()->json(['status' => 'concluido']);
    }

    private function todayAppointments(string $cnes): Builder
    {
        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();

        return DB::table('agendamento')
            ->where('agendamento.cnes_hem', $cnes)
            ->where('data_hora_agendamento', '>=', $today->utc())
            ->where('data_hora_agendamento', '<', $today->addDay()->utc());
    }

    private function appointmentStatus(stdClass $appointment): string
    {
        if ($appointment->status_agendamento === 'cancelado') {
            return 'cancelado';
        }

        if ($appointment->data_hora_coleta !== null || $appointment->status_agendamento === 'finalizado') {
            return 'concluido';
        }

        return $appointment->status_agendamento;
    }
}
