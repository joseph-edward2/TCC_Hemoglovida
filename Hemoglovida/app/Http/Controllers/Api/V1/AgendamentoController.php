<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAgendamentoRequest;
use App\Http\Requests\UpdateAgendamentoRequest;
use App\Http\Resources\AgendamentoResource;
use App\Models\Agendamento;
use App\Models\Evento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgendamentoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'cpf_doador' => ['required', 'string', 'regex:/^\d{11}$/'],
        ]);

        $agendamentos = Agendamento::query()
            ->where('cpf_doador', $validated['cpf_doador'])
            ->with(['evento', 'hemocentro'])
            ->latest('data_hora_agendamento')
            ->get();

        return AgendamentoResource::collection($agendamentos);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAgendamentoRequest $request): AgendamentoResource
    {
        $validated = $request->validated();
        $evento = $this->eventoDoHemocentro($validated['id_evento'], $validated['cnes_hem']);

        $agendamento = DB::transaction(function () use ($validated, $evento): Agendamento {
            $this->garantirHorarioDisponivel($evento->cnes_hem, $validated['data_hora_agendamento']);

            return Agendamento::create([
                'cpf_doador' => $validated['cpf_doador'],
                'cnes_hem' => $evento->cnes_hem,
                'id_evento' => $evento->id_evento,
                'data_hora_agendamento' => $validated['data_hora_agendamento'],
            ]);
        });

        return new AgendamentoResource($agendamento->load(['evento', 'hemocentro']));
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Agendamento $agendamento): AgendamentoResource
    {
        $this->agendamentoDoDoador($request, $agendamento);

        return new AgendamentoResource($agendamento->load(['evento', 'hemocentro']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAgendamentoRequest $request, Agendamento $agendamento): AgendamentoResource
    {
        $validated = $request->validated();
        $this->agendamentoDoDoador($request, $agendamento);

        if ($agendamento->status_agendamento === 'cancelado') {
            throw ValidationException::withMessages([
                'agendamento' => 'Agendamentos cancelados não podem ser alterados.',
            ]);
        }

        $evento = isset($validated['id_evento'])
            ? $this->eventoDoHemocentro($validated['id_evento'], $validated['cnes_hem'])
            : $agendamento->evento;
        $dataHora = $validated['data_hora_agendamento'] ?? $agendamento->data_hora_agendamento;

        DB::transaction(function () use ($agendamento, $evento, $dataHora): void {
            $this->garantirHorarioDisponivel(
                $evento->cnes_hem,
                $dataHora,
                $agendamento->protocolo_agendamento,
            );

            $agendamento->update([
                'cnes_hem' => $evento->cnes_hem,
                'id_evento' => $evento->id_evento,
                'data_hora_agendamento' => $dataHora,
            ]);
        });

        return new AgendamentoResource($agendamento->fresh()->load(['evento', 'hemocentro']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Agendamento $agendamento): AgendamentoResource
    {
        $this->agendamentoDoDoador($request, $agendamento);

        $agendamento->update(['status_agendamento' => 'cancelado']);

        return new AgendamentoResource($agendamento->fresh()->load(['evento', 'hemocentro']));
    }

    private function agendamentoDoDoador(Request $request, Agendamento $agendamento): void
    {
        $validated = $request->validate([
            'cpf_doador' => ['required', 'string', 'regex:/^\d{11}$/'],
        ]);

        abort_unless($agendamento->cpf_doador === $validated['cpf_doador'], 404);
    }

    private function eventoDoHemocentro(int $eventoId, string $cnesHem): Evento
    {
        $evento = Evento::query()
            ->with('hemocentro')
            ->findOrFail($eventoId);

        if ($evento->hemocentro->cnes_hem !== $cnesHem) {
            throw ValidationException::withMessages([
                'id_evento' => 'O evento não pertence ao hemocentro informado.',
            ]);
        }

        return $evento;
    }

    private function garantirHorarioDisponivel(string $cnesHem, mixed $dataHora, ?int $agendamentoId = null): void
    {
        $existeConflito = Agendamento::query()
            ->where('cnes_hem', $cnesHem)
            ->where('data_hora_agendamento', $dataHora)
            ->where('status_agendamento', '!=', 'cancelado')
            ->when($agendamentoId, fn ($query) => $query->where('protocolo_agendamento', '!=', $agendamentoId))
            ->lockForUpdate()
            ->exists();

        if ($existeConflito) {
            throw ValidationException::withMessages([
                'data_hora_agendamento' => 'Este horário não está mais disponível.',
            ]);
        }
    }
}
