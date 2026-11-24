<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgendamentoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'protocolo_agendamento' => $this->protocolo_agendamento,
            'cpf_doador' => $this->cpf_doador,
            'id_evento' => $this->id_evento,
            'cnes_hem' => $this->whenLoaded('hemocentro', fn () => $this->hemocentro->cnes_hem),
            'data_hora_agendamento' => $this->data_hora_agendamento?->toISOString(),
            'data_hora_coleta' => $this->data_hora_coleta?->toISOString(),
            'status_agendamento' => $this->status_agendamento,
            'status_bolsa_doacao' => $this->status_bolsa_doacao,
            'volume_ml_doacao' => $this->volume_ml_doacao,
        ];
    }
}
