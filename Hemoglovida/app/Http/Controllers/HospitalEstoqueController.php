<?php

namespace App\Http\Controllers;

use App\Enums\StatusBolsa;
use App\Http\Requests\AtualizarBolsaRequest;
use App\Http\Resources\BolsaSangueResource;
use App\Models\BolsaSangue;
use App\Models\Hospital;
use App\Services\EstoqueHospitalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HospitalEstoqueController extends Controller
{
    public function __construct(private readonly EstoqueHospitalService $estoque) {}

    public function resumo(Request $request): JsonResponse
    {
        return response()->json($this->estoque->resumo($this->hospital($request)));
    }

    public function consumo(Request $request): JsonResponse
    {
        return response()->json($this->estoque->consumo($this->hospital($request)));
    }

    public function bolsas(Request $request): JsonResponse
    {
        $porPagina = min(100, max(1, (int) $request->query('por_pagina', 20)));
        $pagina = max(1, (int) $request->query('pagina', 1));

        $paginacao = $this->estoque->bolsasQuery($this->hospital($request))
            ->paginate($porPagina, ['*'], 'pagina', $pagina);

        return BolsaSangueResource::collection($paginacao)->response();
    }

    public function bolsa(Request $request): JsonResponse
    {
        $bolsa = $this->bolsaDaRota($request);

        return (new BolsaSangueResource($bolsa))
            ->additional([
                'status_permitidos' => $bolsa->disponibilidade_bolsa->statusPermitidos((bool) $bolsa->vencida),
                'estado_final' => $bolsa->disponibilidade_bolsa->ehFinal(),
            ])
            ->response();
    }

    public function atualizar(AtualizarBolsaRequest $request): JsonResponse
    {
        $bolsa = $this->bolsaDaRota($request);
        $dados = $request->validated();

        $bolsa->paciente_destino_bolsa = $dados['paciente_destino_bolsa'] ?? null;
        $bolsa->aplicarTransicao(StatusBolsa::from($dados['disponibilidade_bolsa']));
        $bolsa->save();

        return (new BolsaSangueResource($bolsa))
            ->additional([
                'status_permitidos' => $bolsa->disponibilidade_bolsa->statusPermitidos((bool) $bolsa->vencida),
                'estado_final' => $bolsa->disponibilidade_bolsa->ehFinal(),
            ])
            ->response();
    }

    private function hospital(Request $request): Hospital
    {
        $hospital = Hospital::daSessao();

        abort_if($hospital === null, 403);

        return $hospital;
    }

    private function bolsaDaRota(Request $request): BolsaSangue
    {
        /** @var BolsaSangue $bolsa */
        $bolsa = $request->route('bolsa');

        return $bolsa;
    }
}
