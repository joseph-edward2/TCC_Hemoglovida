<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalvarEventoRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class HemocentroAlertaController extends Controller
{
    public function index(Request $request): View
    {
        $cnes = $request->user()->cnes_hem;
        $now = now(SalvarEventoRequest::TIMEZONE)->toImmutable();
        $events = DB::table('evento')->where('cnes_hem', $cnes)
            ->where(fn (Builder $query): Builder => $query->whereLike('tipo_evento', 'alerta%')->orWhereLike('tipo_evento', 'campanha%'))
            ->leftJoin('imagem_campanha as imagem', 'imagem.id_evento', '=', 'evento.id_evento')
            ->orderByDesc('data_hora_evento')->orderByDesc('evento.id_evento')->get(['evento.*', 'imagem.id_evento as imagem_id']);

        foreach ($events as $event) {
            $type = Str::lower($event->tipo_evento);
            $event->categoria = str_starts_with($type, 'alerta') ? 'alerta' : 'campanha';
            $event->titulo = $event->titulo_evento ?? $event->tipo_evento;
            $event->inicio = CarbonImmutable::parse($event->data_hora_evento, 'UTC')->setTimezone($now->getTimezone());
            $event->expiracao = $event->expiracao_evento ? CarbonImmutable::parse($event->expiracao_evento, $now->getTimezone()) : null;
            $event->tipos = json_decode($event->tipos_sanguineos ?? '[]', true, flags: JSON_THROW_ON_ERROR);
            $event->expired = $event->expiracao !== null && $event->expiracao->endOfDay() < $now;
            $event->situacao = match (true) {
                $event->status_evento === 'rascunho' => 'Rascunho',
                $event->status_evento === 'pausado' => 'Pausado',
                $event->inicio > $now => 'Programado',
                $event->expired => 'Encerrado',
                default => 'Ativo',
            };
        }

        $editing = null;
        if ($request->has('editar')) {
            $editing = $events->firstWhere('id_evento', $request->query('editar'));
            abort_if($editing === null, 404);
        }

        $alerts = $events->where('categoria', 'alerta');
        $campaigns = $events->where('categoria', 'campanha');
        $notifications = DB::table('notificacao as n')->whereIntegerInRaw('n.id_evento', $alerts->concat($campaigns)->pluck('id_evento'));
        $reached = (clone $notifications)->distinct()->count('n.cpf_doador');
        $responses = $notifications
            ->leftJoin('agendamento as a', function (JoinClause $join) use ($cnes): void {
                $join->on('a.id_evento', '=', 'n.id_evento')->on('a.cpf_doador', '=', 'n.cpf_doador')
                    ->where('a.cnes_hem', $cnes)->where('a.status_agendamento', '<>', 'cancelado');
            })
            ->groupBy('n.id_evento')
            ->select('n.id_evento')
            ->selectRaw('COUNT(DISTINCT n.cpf_doador) AS alcance, 100.0 * COUNT(DISTINCT a.cpf_doador) / COUNT(DISTINCT n.cpf_doador) AS taxa')
            ->get()->keyBy('id_evento');

        return view('hemocentro.Hem_Alertas', [
            'alerts' => $alerts,
            'campaigns' => $campaigns,
            'expiredEvents' => $events->where('expired', true)->sortByDesc('expiracao_evento'),
            'editing' => $editing,
            'activeAlerts' => $alerts->where('situacao', 'Ativo')->count(),
            'activeCampaigns' => $campaigns->where('situacao', 'Ativo')->count(),
            'reached' => $reached,
            'responses' => $responses,
            'responseRate' => $responses->avg('taxa'),
            'bloodTypes' => SalvarEventoRequest::TIPOS_SANGUINEOS,
            'urgencies' => SalvarEventoRequest::URGENCIAS,
            'audiences' => ['alerta' => SalvarEventoRequest::publicos('alerta'), 'campanha' => SalvarEventoRequest::publicos('campanha')],
            'defaultDate' => $now->toDateString(),
            'defaultLocation' => DB::table('hemocentro')->where('cnes_hem', $cnes)->value('endereco_hem'),
        ]);
    }

    public function store(SalvarEventoRequest $request, string $categoria): RedirectResponse
    {
        DB::transaction(function () use ($request, $categoria): void {
            $id = DB::table('evento')->insertGetId($request->dadosEvento() + [
                'cnes_hem' => $request->user()->cnes_hem,
                'tipo_evento' => ucfirst($categoria),
            ], 'id_evento');
            $this->salvarImagem($request, $id);
        });

        return to_route('hemocentro.alertas')->with('status', 'Evento salvo.');
    }

    public function update(SalvarEventoRequest $request, string $categoria, int $evento): RedirectResponse
    {
        $query = $this->evento($request, $categoria, $evento);
        DB::transaction(function () use ($query, $request, $evento): void {
            $event = $query->lockForUpdate()->firstOrFail(['data_hora_evento']);
            $query->update($request->dadosEvento($event->data_hora_evento));
            $this->salvarImagem($request, $evento);
        });

        return to_route('hemocentro.alertas')->with('status', 'Evento atualizado.');
    }

    private function salvarImagem(SalvarEventoRequest $request, int $evento): void
    {
        $imagem = $request->file('imagem');
        if ($imagem !== null) {
            DB::table('imagem_campanha')->updateOrInsert(['id_evento' => $evento], [
                'mime' => $imagem->getMimeType(),
                'conteudo_base64' => base64_encode($imagem->getContent()),
            ]);
        }
    }

    public function imagem(Request $request, int $evento): Response
    {
        $imagem = DB::table('imagem_campanha')->whereIn('id_evento', $this->evento($request, 'campanha', $evento)->select('id_evento'))
            ->firstOrFail(['mime', 'conteudo_base64']);

        return response(base64_decode($imagem->conteudo_base64), 200, [
            'Content-Type' => $imagem->mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function status(Request $request, string $categoria, int $evento): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', 'string', 'in:publicado,pausado']])['status'];
        $query = $this->evento($request, $categoria, $evento);
        if ($query->update(['status_evento' => $status]) === 0) {
            abort_unless($query->exists(), 404);
        }

        return to_route('hemocentro.alertas')->with('status', $status === 'publicado' ? 'Evento publicado.' : 'Evento pausado.');
    }

    public function destroy(Request $request, string $categoria, int $evento): RedirectResponse
    {
        $query = $this->evento($request, $categoria, $evento);
        $deletable = clone $query;
        foreach (['agendamento', 'notificacao'] as $table) {
            $deletable->whereNotExists(fn (Builder $linked): Builder => $linked->selectRaw('1')->from($table)
                ->whereColumn($table.'.id_evento', 'evento.id_evento'));
        }
        if ($deletable->delete() === 0) {
            abort_unless($query->exists(), 404);

            return to_route('hemocentro.alertas')->withErrors(['evento' => 'Este evento possui agendamentos ou notificações vinculados e não pode ser excluído.']);
        }

        return to_route('hemocentro.alertas')->with('status', 'Evento excluído.');
    }

    private function evento(Request $request, string $categoria, int $evento): Builder
    {
        return DB::table('evento')->where('cnes_hem', $request->user()->cnes_hem)
            ->whereLike('tipo_evento', $categoria.'%')->where('id_evento', $evento);
    }
}
