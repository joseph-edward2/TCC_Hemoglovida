<?php

namespace App\Http\Resources;

use App\Services\EstoqueHospitalService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BolsaSangueResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'isbt' => $this->isbt,
            'isbt_formatado' => EstoqueHospitalService::formatarIsbt($this->isbt),
            'tipo_bolsa' => $this->tipo_bolsa?->value,
            'origem' => EstoqueHospitalService::origem($this->resource),
            'data_hora_recebimento_bolsa' => $this->data_hora_recebimento_bolsa?->toISOString(),
            'data_venc_bolsa' => $this->data_venc_bolsa?->toDateString(),
            'disponibilidade_bolsa' => $this->disponibilidade_bolsa?->value,
            'disponibilidade_label' => $this->disponibilidade_bolsa?->label(),
            'vencida' => (bool) $this->vencida,
            'data_hora_envio_bolsa' => $this->when(
                in_array($request->route()?->getName(), [
                    'hospital.estoque.bolsa',
                    'hospital.estoque.bolsa.update',
                ], true),
                fn () => $this->data_hora_envio_bolsa?->toISOString(),
            ),
            'paciente_destino_bolsa' => $this->when(
                in_array($request->route()?->getName(), [
                    'hospital.estoque.bolsa',
                    'hospital.estoque.bolsa.update',
                ], true),
                fn () => $this->paciente_destino_bolsa,
            ),
        ];
    }
}
