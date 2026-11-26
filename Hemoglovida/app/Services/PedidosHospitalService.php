<?php

namespace App\Services;

use App\Enums\StatusBolsa;
use App\Enums\StatusPedido;
use App\Enums\TipoSanguineo;
use App\Http\Requests\StorePedidoRequest;
use App\Http\Resources\BolsaSangueResource;
use App\Http\Resources\PedidoResource;
use App\Models\BolsaSangue;
use App\Models\Hemocentro;
use App\Models\Hospital;
use App\Models\Pedido;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PedidosHospitalService
{
    public function __construct(private readonly EstoqueHospitalService $estoque) {}

    /**
     * @return array<string, int|string>
     */
    public function resumo(Hospital $hospital): array
    {
        $cnpjHosp = $hospital->cnpj_hosp;
        $pedidos = Pedido::query()->doHospital($cnpjHosp);

        $pendentes = (clone $pedidos)
            ->whereIn('status_ped', [
                StatusPedido::Pendente->value,
                StatusPedido::EmAnalise->value,
            ])
            ->count();
        $emSeparacao = (clone $pedidos)->where('status_ped', StatusPedido::EmSeparacao->value)->count();
        $enviados = (clone $pedidos)->where('status_ped', StatusPedido::Enviado->value)->count();
        $criticos = (clone $pedidos)
            ->where('urgencia_ped', 'critica')
            ->where('status_ped', '!=', StatusPedido::Entregue->value)
            ->count();

        return [
            'pendentes' => $pendentes,
            'em_separacao' => $emSeparacao,
            'enviados' => $enviados,
            'criticos' => $criticos,
            'atualizado_em' => now()->toISOString(),
        ];
    }

    public function listar(Hospital $hospital, int $porPagina, int $pagina): AnonymousResourceCollection
    {
        $cnpjHosp = $hospital->cnpj_hosp;

        $paginacao = Pedido::query()
            ->doHospital($cnpjHosp)
            ->with('hemocentro')
            ->withCount([
                'bolsas as bolsas_recebidas' => fn (Builder $query): Builder => $query
                    ->where('cnpj_hosp', $cnpjHosp)
                    ->whereNotNull('data_hora_recebimento_bolsa'),
                'bolsas as bolsas_em_transito' => fn (Builder $query): Builder => $query
                    ->where('cnpj_hosp', $cnpjHosp)
                    ->emTransito(),
            ])
            ->orderByRaw('CASE WHEN status_ped = ? THEN 0 ELSE 1 END', [StatusPedido::Enviado->value])
            ->orderByDesc('bolsas_em_transito')
            ->orderByDesc('data_hora_ped')
            ->paginate(min(50, max(1, $porPagina)), ['*'], 'pagina', max(1, $pagina));

        return PedidoResource::collection($paginacao);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function hemocentros(?string $tipoSanguineo, int $quantidade): array
    {
        $tipo = $tipoSanguineo === null ? null : TipoSanguineo::tryFrom($tipoSanguineo);

        $hemocentros = Hemocentro::query()
            ->withCount([
                'bolsas as estoque_disponivel' => fn (Builder $query): Builder => $query
                    ->whereNull('cnpj_hosp')
                    ->disponiveis()
                    ->where('data_venc_bolsa', '>=', today()->toDateString())
                    ->when($tipo !== null, fn (Builder $query): Builder => $query
                        ->where('tipo_bolsa', $tipo?->value))
                    ->when($tipo === null, fn (Builder $query): Builder => $query->whereRaw('1 = 0')),
            ])
            ->orderBy('nome_hem')
            ->orderBy('cnes_hem')
            ->get();

        return $hemocentros->map(function (Hemocentro $hemocentro) use ($tipo, $quantidade): array {
            $disponiveis = (int) $hemocentro->estoque_disponivel;
            $situacao = $tipo === null
                ? null
                : match (true) {
                    $disponiveis >= $quantidade => 'Estoque Disponível',
                    $disponiveis > 0 => 'Estoque Baixo',
                    default => 'Sem estoque',
                };

            return [
                'cnes_hem' => $hemocentro->cnes_hem,
                'rotulo' => $hemocentro->rotulo,
                'endereco' => $hemocentro->endereco_hem,
                'contato' => $hemocentro->contato_hem,
                'estoque' => [
                    'quantidade' => $disponiveis,
                    'situacao' => $situacao,
                ],
            ];
        })->all();
    }

    public function criar(Hospital $hospital, StorePedidoRequest $request): Pedido
    {
        $arquivo = $request->file('requisicao');

        return DB::transaction(function () use ($hospital, $request, $arquivo): Pedido {
            $pedido = new Pedido;
            $pedido->cnpj_hosp = $hospital->cnpj_hosp;
            $pedido->cnes_hem = $request->input('cnes_hem');
            $pedido->cod_reg_fun = Auth::guard('funcionario')->user()?->cod_reg_fun;
            $pedido->desc_ped = $request->input('paciente_setor');
            $pedido->data_hora_ped = now();
            $pedido->status_ped = StatusPedido::Pendente->value;
            $pedido->quant_ped = (int) $request->input('quantidade');
            $pedido->tipo_sanguineo_ped = $request->input('tipo_sanguineo');
            $pedido->urgencia_ped = $request->input('urgencia');
            $pedido->url_requisicao_transfusao_ped = $arquivo->store(
                'requisicoes/'.$hospital->cnpj_hosp,
                'local',
            );
            $pedido->save();

            return $pedido->load('hemocentro');
        });
    }

    /**
     * @return Collection<int, BolsaSangue>
     */
    public function bolsasEmTransito(Hospital $hospital, Pedido $pedido): Collection
    {
        return $pedido->bolsas()
            ->doHospital($hospital->cnpj_hosp)
            ->emTransito()
            ->with('hemocentro')
            ->orderBy('isbt')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function confirmarChegada(Hospital $hospital, Pedido $pedido): array
    {
        $cnpjHosp = $hospital->cnpj_hosp;

        return DB::transaction(function () use ($cnpjHosp, $pedido): array {
            /** @var Pedido $pedidoBloqueado */
            $pedidoBloqueado = Pedido::query()
                ->doHospital($cnpjHosp)
                ->whereKey($pedido->id_ped)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var Collection<int, BolsaSangue> $bolsas */
            $bolsas = BolsaSangue::query()
                ->doHospital($cnpjHosp)
                ->where('id_ped', $pedidoBloqueado->id_ped)
                ->emTransito()
                ->with('hemocentro')
                ->lockForUpdate()
                ->get();

            abort_if($bolsas->isEmpty(), 409, 'Não há bolsas em trânsito para confirmar.');

            abort_if(
                $pedidoBloqueado->status_ped !== StatusPedido::Enviado,
                422,
                'Só é possível confirmar a chegada de um pedido enviado.',
            );

            foreach ($bolsas as $bolsa) {
                $bolsa->aplicarTransicao(StatusBolsa::Disponivel);
                $bolsa->save();
            }

            $recebidas = (int) $pedidoBloqueado->bolsas()
                ->doHospital($cnpjHosp)
                ->whereNotNull('data_hora_recebimento_bolsa')
                ->count();

            $entregaParcial = $recebidas < $pedidoBloqueado->quant_ped;

            if (! $entregaParcial) {
                $pedidoBloqueado->status_ped = StatusPedido::Entregue->value;
                $pedidoBloqueado->data_hora_entrega_ped = now();
                $pedidoBloqueado->save();
            }

            $pedidoBloqueado->setAttribute('bolsas_recebidas', $recebidas);
            $pedidoBloqueado->setAttribute('bolsas_em_transito', 0);

            return [
                'bolsas_adicionadas' => $bolsas->count(),
                'bolsas_recebidas' => $recebidas,
                'entrega_parcial' => $entregaParcial,
                'pedido' => $pedidoBloqueado->load('hemocentro'),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function bolsasEmTransitoPayload(Hospital $hospital, Pedido $pedido): array
    {
        return BolsaSangueResource::collection($this->bolsasEmTransito($hospital, $pedido))
            ->resolve();
    }
}
