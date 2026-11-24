<?php

namespace App\Models;

use Database\Factories\NotificacaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notificacao extends Model
{
    /** @use HasFactory<NotificacaoFactory> */
    use HasFactory;

    protected $table = 'notificacao';

    protected $primaryKey = 'id_notificacao';

    public $timestamps = false;

    protected $fillable = [
        'cpf_doador',
        'id_evento',
        'protocolo_agendamento',
        'tipo_notificacao',
        'dist_notificacao',
        'criada_em',
    ];

    protected function casts(): array
    {
        return [
            'criada_em' => 'datetime',
        ];
    }

    public function doador(): BelongsTo
    {
        return $this->belongsTo(Doador::class, 'cpf_doador', 'cpf_doador');
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'id_evento', 'id_evento');
    }

    public function agendamento(): BelongsTo
    {
        return $this->belongsTo(Agendamento::class, 'protocolo_agendamento', 'protocolo_agendamento');
    }
}
