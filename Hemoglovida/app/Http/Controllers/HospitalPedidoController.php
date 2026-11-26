<?php

namespace App\Http\Controllers;

use App\Enums\TipoSanguineo;
use App\Http\Requests\StorePedidoRequest;
use App\Http\Resources\PedidoResource;
use App\Models\Hospital;
use App\Models\Pedido;
use App\Services\PedidosHospitalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Enum;

class HospitalPedidoController extends Controller
{
    public function __construct(private readonly PedidosHospitalService $pedidos) {}

    public function resumo(Request $request): JsonResponse
    {
        return response()->json($this->pedidos->resumo($this->hospital($request)));
    }

    public function listar(Request $request): JsonResponse
    {
        $pedidos = $this->pedidos->listar(
            $this->hospital($request),
            (int) $request->query('por_pagina', 5),
            (int) $request->query('pagina', 1),
        );

        return $pedidos->response();
    }

    public function hemocentros(Request $request): JsonResponse
    {
        $this->hospital($request);

        $dados = $request->validate([
            'tipo' => ['nullable', new Enum(TipoSanguineo::class)],
            'quantidade' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return response()->json($this->pedidos->hemocentros(
            $dados['tipo'] ?? null,
            (int) ($dados['quantidade'] ?? 1),
        ));
    }

    public function store(StorePedidoRequest $request): JsonResponse
    {
        $pedido = $this->pedidos->criar($this->hospital($request), $request);

        return (new PedidoResource($pedido))->response()->setStatusCode(201);
    }

    public function requisicao(Request $request): Response
    {
        $pedido = $this->pedidoDaRota($request);
        $caminho = $pedido->url_requisicao_transfusao_ped;

        abort_if(
            blank($caminho) || ! Storage::disk('local')->exists($caminho),
            404,
            'Requisição em PDF não encontrada.',
        );

        return response(Storage::disk('local')->get($caminho), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="requisicao-'.$pedido->id_ped.'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function bolsasEmTransito(Request $request): JsonResponse
    {
        $hospital = $this->hospital($request);

        return response()->json($this->pedidos->bolsasEmTransitoPayload(
            $hospital,
            $this->pedidoDaRota($request),
        ));
    }

    public function confirmarChegada(Request $request): JsonResponse
    {
        $resultado = $this->pedidos->confirmarChegada(
            $this->hospital($request),
            $this->pedidoDaRota($request),
        );
        $pedido = $resultado['pedido'];
        unset($resultado['pedido']);

        return response()->json(array_merge($resultado, [
            'pedido' => (new PedidoResource($pedido))->resolve(),
        ]));
    }

    private function hospital(Request $request): Hospital
    {
        $hospital = Hospital::daSessao();

        abort_if($hospital === null, 403);

        return $hospital;
    }

    private function pedidoDaRota(Request $request): Pedido
    {
        /** @var Pedido $pedido */
        $pedido = $request->route('pedido');

        return $pedido;
    }
}
