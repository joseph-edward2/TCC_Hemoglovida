<?php

namespace App\Http\Controllers;

use App\Models\Funcionario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use stdClass;

class HemocentroPedidoController extends Controller
{
    private const ETAPAS = [
        'pendente' => 'Recebido',
        'em_separacao' => 'Em Separação',
        'enviado' => 'Enviado',
        'entregue' => 'Entregue',
    ];

    private const STATUS = self::ETAPAS + ['recusado' => 'Recusado'];

    private const URGENCIAS = ['critica' => 'Crítico', 'urgente' => 'Urgente', 'rotina' => 'Rotina'];

    private const TIPOS_SANGUINEOS = ['O-', 'O+', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'];

    private const FUSO = 'America/Sao_Paulo';

    private const FILA_CRITICA = "COALESCE(urgencia_ped, '') = 'critica' AND status_ped IN ('pendente', 'em_separacao', 'enviado')";

    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'status' => ['nullable', 'string', Rule::in(array_keys(self::STATUS))],
            'tipo_sanguineo' => ['nullable', 'string', Rule::in(self::TIPOS_SANGUINEOS)],
            'urgencia' => ['nullable', 'string', Rule::in(array_keys(self::URGENCIAS))],
        ]);

        $pedidos = DB::table('pedido')->where('pedido.cnes_hem', $request->user()->cnes_hem);
        $hoje = now(self::FUSO)->toImmutable()->startOfDay();
        $totais = (clone $pedidos)
            ->selectRaw("COUNT(CASE WHEN status_ped = 'pendente' THEN 1 END) AS pendentes")
            ->selectRaw("COUNT(CASE WHEN status_ped = 'em_separacao' THEN 1 END) AS em_separacao")
            ->selectRaw('COUNT(CASE WHEN data_hora_envio_ped >= ? AND data_hora_envio_ped < ? THEN 1 END) AS enviados_hoje', [
                $hoje->utc(), $hoje->addDay()->utc(),
            ])
            ->selectRaw('COUNT(CASE WHEN '.self::FILA_CRITICA.' THEN 1 END) AS criticos')
            ->first();

        $pedidos->join('hospital', 'hospital.cnpj_hosp', '=', 'pedido.cnpj_hosp')
            ->leftJoin('funcionario', function (JoinClause $join): void {
                $join->on('funcionario.cod_reg_fun', '=', 'pedido.cod_reg_fun')
                    ->on('funcionario.cnes_hem', '=', 'pedido.cnes_hem');
            })
            ->select('pedido.*', 'hospital.nome_hosp', 'funcionario.nome_fun')
            ->orderByDesc('data_hora_ped')->orderByDesc('id_ped');

        foreach ($filtros as $campo => $valor) {
            if ($valor !== null) {
                $pedidos->where('pedido.'.$campo.'_ped', $valor);
            }
        }

        $criticos = (clone $pedidos)->whereRaw(self::FILA_CRITICA)
            ->cursorPaginate(5, cursorName: 'criticos')->appends($filtros)->through($this->prepararPedido(...));
        $recentes = $pedidos->whereRaw('NOT ('.self::FILA_CRITICA.')')
            ->cursorPaginate(5, cursorName: 'recentes')->appends($filtros)->through($this->prepararPedido(...));

        return view('hemocentro.Hem_Pedidos', [
            'reservas' => DB::table('pedido_bolsa')->whereIn('id_ped', $criticos->pluck('id_ped')->concat($recentes->pluck('id_ped')))
                ->orderBy('isbt')->get()->groupBy('id_ped'),
            'totais' => $totais,
            'criticos' => $criticos,
            'recentes' => $recentes,
            'filtros' => $filtros,
            'status' => self::STATUS,
            'etapas' => self::ETAPAS,
            'urgencias' => self::URGENCIAS,
            'tiposSanguineos' => self::TIPOS_SANGUINEOS,
        ]);
    }

    private function bolsasDisponiveis(stdClass $pedido): Builder
    {
        $tipo = strtoupper(trim($pedido->tipo_sanguineo_ped ?? ''));

        return DB::table('bolsa_sangue')->where('cnes_hem', $pedido->cnes_hem)
            ->whereRaw('UPPER(TRIM(tipo_bolsa)) = ?', [$tipo])
            ->whereIn(DB::raw('UPPER(TRIM(tipo_bolsa))'), self::TIPOS_SANGUINEOS)
            ->whereRaw("LOWER(REPLACE(REPLACE(TRIM(disponibilidade_bolsa), 'Í', 'i'), 'í', 'i')) = 'disponivel'")
            ->where('quant_bolsa', '>', 0)
            ->where(fn (Builder $query): Builder => $query->whereNull('data_venc_bolsa')
                ->orWhere('data_venc_bolsa', '>=', now(self::FUSO)->toDateString()));
    }

    public function bolsas(Request $request, int $pedido): View
    {
        $registro = DB::table('pedido')->where('id_ped', $pedido)->where('cnes_hem', $request->user()->cnes_hem)->firstOrFail();
        abort_unless($registro->status_ped === 'pendente', 409);

        return view('hemocentro.bolsas-pedido', [
            'pedido' => $registro,
            'bolsas' => $this->bolsasDisponiveis($registro)->orderByRaw('CASE WHEN data_venc_bolsa IS NULL THEN 1 ELSE 0 END')
                ->orderBy('data_venc_bolsa')->orderBy('isbt')->get(['isbt', 'quant_bolsa', 'data_venc_bolsa']),
        ]);
    }

    private function separar(Request $request, Builder $registro): void
    {
        DB::transaction(function () use ($request, $registro): void {
            $pedido = (clone $registro)->lockForUpdate()->firstOrFail();
            if ($pedido->status_ped === 'em_separacao') {
                return;
            }
            if ($pedido->status_ped !== 'pendente') {
                throw ValidationException::withMessages(['pedido' => 'O status atual do pedido não permite esta ação.']);
            }
            $dados = $request->validate(['bolsas' => ['required', 'array'], 'bolsas.*' => ['nullable', 'integer', 'min:0']], [
                'bolsas.required' => 'Selecione as bolsas para o pedido.',
            ]);
            $selecionadas = array_filter($dados['bolsas']);
            if ($selecionadas === [] || array_sum($selecionadas) !== (int) $pedido->quant_ped) {
                throw ValidationException::withMessages(['bolsas' => 'Selecione a quantidade exata solicitada no pedido.']);
            }
            ksort($selecionadas, SORT_STRING);
            foreach ($selecionadas as $isbt => $quantidade) {
                $disponiveis = $this->bolsasDisponiveis($pedido)->where('isbt', (string) $isbt)->where('quant_bolsa', '>=', $quantidade);
                $bolsa = (clone $disponiveis)->lockForUpdate()->first(['quant_bolsa']);
                if ($bolsa === null) {
                    throw ValidationException::withMessages(['bolsas' => 'Estoque alterado ou bolsa indisponível. Confira as bolsas novamente.']);
                }
                $restante = $bolsa->quant_bolsa - $quantidade;
                $disponiveis->update(['quant_bolsa' => $restante, 'disponibilidade_bolsa' => $restante === 0 ? 'reservado' : 'disponivel']);
                DB::table('pedido_bolsa')->insert(['id_ped' => $pedido->id_ped, 'isbt' => (string) $isbt, 'quantidade' => $quantidade]);
            }
            $usuario = $request->user();
            $registro->update(['status_ped' => 'em_separacao', 'cod_reg_fun' => $usuario instanceof Funcionario ? $usuario->cod_reg_fun : $pedido->cod_reg_fun]);
        }, 3);
    }

    public function status(Request $request, int $pedido): RedirectResponse
    {
        $destino = $request->validate([
            'status' => ['required', 'string', Rule::in(['em_separacao', 'enviado', 'recusado'])],
        ], ['status.in' => 'Selecione uma ação válida para o pedido.'])['status'];

        $usuario = $request->user();
        $registro = DB::table('pedido')->where('cnes_hem', $usuario->cnes_hem)->where('id_ped', $pedido);
        if ($destino === 'em_separacao') {
            try {
                $this->separar($request, $registro);
            } catch (ValidationException $erro) {
                return back(fallback: route('hemocentro.pedidos'))->withErrors($erro->errors());
            }

            return back(fallback: route('hemocentro.pedidos'))->with('status', "Pedido #{$pedido} em separação.");
        }
        if ($destino === 'recusado') {
            $atualizados = DB::transaction(function () use ($registro, $request, $pedido): int {
                $alterados = (clone $registro)->where('status_ped', 'pendente')->update(['status_ped' => 'recusado']);
                if ($alterados === 1) {
                    $dados = $request->validate(['motivo' => ['required', 'string', 'max:4000']], [
                        'motivo.required' => 'Informe o motivo da recusa.',
                        'motivo.max' => 'O motivo deve ter até 4000 caracteres.',
                    ]);
                    DB::table('mensagem_recusa_pedido')->insert([
                        'id_ped' => $pedido,
                        'cnpj_hosp' => $registro->value('cnpj_hosp'),
                        'motivo' => $dados['motivo'],
                    ]);
                }

                return $alterados;
            }, 3);
        } else {
            $atualizados = (clone $registro)->where('status_ped', 'em_separacao')
                ->update(['status_ped' => 'enviado', 'data_hora_envio_ped' => now('UTC')]);
        }
        $retorno = back(fallback: route('hemocentro.pedidos'));
        if ($atualizados === 0) {
            $atual = $registro->value('status_ped');
            abort_if($atual === null, 404);
            if ($atual !== $destino) {
                return $retorno->withErrors(['pedido' => 'O status atual do pedido não permite esta ação.']);
            }
        }

        return $retorno->with('status', "Pedido #{$pedido}: ".self::STATUS[$destino].'.');
    }

    private function prepararPedido(stdClass $pedido): stdClass
    {
        $pedido->quantidade = $pedido->quant_ped.((int) $pedido->quant_ped === 1 ? ' bolsa' : ' bolsas');
        $pedido->solicitadoEm = CarbonImmutable::parse($pedido->data_hora_ped, 'UTC')->setTimezone(self::FUSO)->format('d/m/Y H:i');
        $pedido->enviadoEm = $pedido->data_hora_envio_ped === null ? null
            : CarbonImmutable::parse($pedido->data_hora_envio_ped, 'UTC')->setTimezone(self::FUSO)->format('d/m/Y H:i');
        $pedido->situacao = self::STATUS[$pedido->status_ped] ?? Str::ucfirst(str_replace('_', ' ', $pedido->status_ped));
        $pedido->urgencia = self::URGENCIAS[$pedido->urgencia_ped] ?? $pedido->urgencia_ped ?? 'Não informada';
        $pedido->requisicao = $this->urlRequisicao($pedido->url_requisicao_transfusao_ped);

        return $pedido;
    }

    private function urlRequisicao(?string $valor): ?string
    {
        $valor = trim($valor ?? '');
        if (Str::isUrl($valor, ['http', 'https'])) {
            return $valor;
        }
        if ($valor === '' || preg_match('~^(?:[a-z][a-z0-9+.-]*:|//)|[[:cntrl:]\\\\]~i', $valor)) {
            return null;
        }

        return url('/'.ltrim($valor, '/'));
    }
}
