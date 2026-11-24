<?php

namespace App\Models;

use Database\Factories\HospitalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Hospital extends Authenticatable
{
    /** @use HasFactory<HospitalFactory> */
    use HasFactory;

    protected $table = 'hospital';

    protected $primaryKey = 'cnpj_hosp';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'cnpj_hosp',
        'cnes_hosp',
        'nome_hosp',
        'contato_hosp',
        'endereco_hosp',
        'senha_hosp',
    ];

    protected $hidden = [
        'senha_hosp',
    ];

    public function getAuthPasswordName(): string
    {
        return 'senha_hosp';
    }

    protected function casts(): array
    {
        return [
            'senha_hosp' => 'hashed',
        ];
    }

    public function funcionarios(): HasMany
    {
        return $this->hasMany(Funcionario::class, 'cnpj_hosp', 'cnpj_hosp');
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'cnpj_hosp', 'cnpj_hosp');
    }

    public function bolsas(): HasMany
    {
        return $this->hasMany(BolsaSangue::class, 'cnpj_hosp', 'cnpj_hosp');
    }
}
