<?php

namespace App\Models;

use Database\Factories\EventoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evento extends Model
{
    /** @use HasFactory<EventoFactory> */
    use HasFactory;

    protected $table = 'evento';

    protected $primaryKey = 'id_evento';

    public $timestamps = false;

    protected $fillable = [
        'cnes_hem',
        'tipo_evento',
        'data_hora_evento',
        'local_evento',
        'desc_evento',
    ];

    protected function casts(): array
    {
        return [
            'data_hora_evento' => 'datetime',
        ];
    }

    public function hemocentro(): BelongsTo
    {
        return $this->belongsTo(Hemocentro::class, 'cnes_hem', 'cnes_hem');
    }

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class, 'id_evento', 'id_evento');
    }
}
