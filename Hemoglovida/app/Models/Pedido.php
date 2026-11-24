<?php

namespace App\Models;

use App\Enums\StatusPedido;
use App\Enums\TipoSanguineo;
use App\Enums\UrgenciaPedido;
use Database\Factories\PedidoFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pedido extends Model
{
    /** @use HasFactory<PedidoFactory> */
    use HasFactory;

    protected $table = 'pedido';

    protected $primaryKey = 'id_ped';

    public $timestamps = false;

    protected $fillable = [
        'cnpj_hosp',
        'cnes_hem',
        'cod_reg_fun',
        'desc_ped',
        'data_hora_ped',
        'status_ped',
        'quant_ped',
        'url_requisicao_transfusao_ped',
        'tipo_sanguineo_ped',
        'urgencia_ped',
        'data_hora_envio_ped',
        'data_hora_entrega_ped',
    ];

    protected $attributes = [
        'status_ped' => 'pendente',
    ];

    protected function casts(): array
    {
        return [
            'data_hora_ped' => 'datetime',
            'quant_ped' => 'integer',
            'status_ped' => StatusPedido::class,
            'urgencia_ped' => UrgenciaPedido::class,
            'tipo_sanguineo_ped' => TipoSanguineo::class,
            'data_hora_envio_ped' => 'datetime',
            'data_hora_entrega_ped' => 'datetime',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class, 'cnpj_hosp', 'cnpj_hosp');
    }

    public function hemocentro(): BelongsTo
    {
        return $this->belongsTo(Hemocentro::class, 'cnes_hem', 'cnes_hem');
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class, 'cod_reg_fun', 'cod_reg_fun');
    }

    public function bolsas(): HasMany
    {
        return $this->hasMany(BolsaSangue::class, 'id_ped', 'id_ped');
    }

    #[Scope]
    protected function doHospital(Builder $query, string $cnpjHosp): Builder
    {
        return $query->where('cnpj_hosp', $cnpjHosp);
    }

    #[Scope]
    protected function pendentes(Builder $query): Builder
    {
        return $query->where('status_ped', StatusPedido::Pendente->value);
    }

    #[Scope]
    protected function criticos(Builder $query): Builder
    {
        return $query->where('urgencia_ped', UrgenciaPedido::Critica->value);
    }

    #[Scope]
    protected function doHemocentro(Builder $query, string $cnesHem): Builder
    {
        return $query->where('cnes_hem', $cnesHem);
    }
}
