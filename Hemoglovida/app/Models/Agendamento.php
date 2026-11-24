<?php

namespace App\Models;

use Database\Factories\AgendamentoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Agendamento extends Model
{
    /** @use HasFactory<AgendamentoFactory> */
    use HasFactory;

    protected $table = 'agendamento';

    protected $primaryKey = 'protocolo_agendamento';

    public $timestamps = false;

    protected $fillable = [
        'cpf_doador',
        'cnes_hem',
        'id_evento',
        'data_hora_agendamento',
        'data_hora_coleta',
        'status_agendamento',
        'status_bolsa_doacao',
        'volume_ml_doacao',
    ];

    protected $attributes = [
        'status_agendamento' => 'agendado',
    ];

    protected function casts(): array
    {
        return [
            'data_hora_agendamento' => 'datetime',
            'data_hora_coleta' => 'datetime',
            'volume_ml_doacao' => 'float',
        ];
    }

    public function doador(): BelongsTo
    {
        return $this->belongsTo(Doador::class, 'cpf_doador', 'cpf_doador');
    }

    public function hemocentro(): BelongsTo
    {
        return $this->belongsTo(Hemocentro::class, 'cnes_hem', 'cnes_hem');
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'id_evento', 'id_evento');
    }
}
