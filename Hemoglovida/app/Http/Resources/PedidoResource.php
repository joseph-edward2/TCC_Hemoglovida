<?php

namespace App\Http\Resources;

use App\Enums\StatusPedido;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PedidoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $temRequisicao = filled($this->url_requisicao_transfusao_ped);
        $bolsasEmTransito = (int) ($this->bolsas_em_transito ?? 0);

        return [
            'id_ped' => $this->id_ped,
            'tipo_sanguineo_ped' => $this->tipo_sanguineo_ped?->value,
            'quant_ped' => $this->quant_ped,
            'urgencia_ped' => $this->urgencia_ped?->value,
            'urgencia_label' => $this->urgencia_ped?->label(),
            'status_ped' => $this->status_ped?->value,
            'status_label' => $this->status_ped?->label(),
            'etapa' => $this->status_ped?->etapa(),
            'desc_ped' => $this->desc_ped,
            'data_hora_ped' => $this->data_hora_ped?->toISOString(),
            'hemocentro' => [
                'cnes_hem' => $this->hemocentro?->cnes_hem,
                'rotulo' => $this->hemocentro?->rotulo,
                'contato' => $this->hemocentro?->contato_hem,
            ],
            'tem_requisicao' => $temRequisicao,
            'url_requisicao' => $this->when(
                $temRequisicao,
                fn (): string => route('hospital.pedidos.requisicao', $this->id_ped),
            ),
            'bolsas_recebidas' => (int) ($this->bolsas_recebidas ?? 0),
            'bolsas_em_transito' => $bolsasEmTransito,
            'pode_confirmar_chegada' => $this->status_ped === StatusPedido::Enviado && $bolsasEmTransito > 0,
        ];
    }
}
