<?php

namespace App\Models;

use App\Enums\StatusBolsa;
use App\Enums\TipoSanguineo;
use Database\Factories\BolsaSangueFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BolsaSangue extends Model
{
    /** @use HasFactory<BolsaSangueFactory> */
    use HasFactory;

    protected $table = 'bolsa_sangue';

    protected $primaryKey = 'isbt';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'isbt',
        'cnes_hem',
        'quant_bolsa',
        'tipo_bolsa',
        'disponibilidade_bolsa',
        'data_venc_bolsa',
        'cnpj_hosp',
        'id_ped',
        'data_hora_envio_bolsa',
        'data_hora_recebimento_bolsa',
        'paciente_destino_bolsa',
    ];

    protected $attributes = [
        'disponibilidade_bolsa' => 'disponivel',
    ];

    protected function casts(): array
    {
        return [
            'quant_bolsa' => 'integer',
            'data_venc_bolsa' => 'date',
            'tipo_bolsa' => TipoSanguineo::class,
            'disponibilidade_bolsa' => StatusBolsa::class,
            'data_hora_envio_bolsa' => 'datetime',
            'data_hora_recebimento_bolsa' => 'datetime',
        ];
    }

    public function hemocentro(): BelongsTo
    {
        return $this->belongsTo(Hemocentro::class, 'cnes_hem', 'cnes_hem');
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class, 'cnpj_hosp', 'cnpj_hosp');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'id_ped', 'id_ped');
    }

    #[Scope]
    protected function doHospital(Builder $query, string $cnpjHosp): Builder
    {
        return $query->where('cnpj_hosp', $cnpjHosp);
    }

    #[Scope]
    protected function emTransito(Builder $query): Builder
    {
        return $query->where('disponibilidade_bolsa', StatusBolsa::EmTransito->value);
    }

    #[Scope]
    protected function disponiveis(Builder $query): Builder
    {
        return $query->where('disponibilidade_bolsa', StatusBolsa::Disponivel->value);
    }

    #[Scope]
    protected function aVencer(Builder $query, ?int $dias = null): Builder
    {
        $dias ??= (int) config('estoque.dias_a_vencer', 7);

        return $query->whereBetween('data_venc_bolsa', [
            today()->toDateString(),
            today()->addDays($dias)->toDateString(),
        ]);
    }

    #[Scope]
    protected function vencidas(Builder $query): Builder
    {
        return $query->where('data_venc_bolsa', '<', today()->toDateString());
    }
}
