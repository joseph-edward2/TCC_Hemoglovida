<?php

namespace App\Http\Controllers;

use App\Models\Funcionario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    public function status(Request $request, int $pedido): RedirectResponse
    {
        $destino = $request->validate([
            'status' => ['required', 'string', Rule::in(['em_separacao', 'enviado', 'recusado'])],
        ], ['status.in' => 'Selecione uma ação válida para o pedido.'])['status'];

        $usuario = $request->user();
        $registro = DB::table('pedido')->where('cnes_hem', $usuario->cnes_hem)->where('id_ped', $pedido);
        $origem = $destino === 'enviado' ? 'em_separacao' : 'pendente';
        $alteracoes = ['status_ped' => $destino];
        if ($destino === 'enviado') {
            $alteracoes['data_hora_envio_ped'] = now('UTC');
        } elseif ($destino === 'em_separacao' && $usuario instanceof Funcionario) {
            $alteracoes['cod_reg_fun'] = $usuario->cod_reg_fun;
        }

        $atualizados = (clone $registro)->where('status_ped', $origem)->update($alteracoes);
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
