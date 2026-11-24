<?php

namespace App\Models;

use Database\Factories\HemocentroFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Hidden(['senha_hem'])]
class Hemocentro extends Authenticatable
{
    /** @use HasFactory<HemocentroFactory> */
    use HasFactory;

    protected $table = 'hemocentro';

    protected $primaryKey = 'cnes_hem';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'cnes_hem',
        'cnpj_hem',
        'endereco_hem',
        'contato_hem',
        'senha_hem',
    ];

    public function getAuthPasswordName(): string
    {
        return 'senha_hem';
    }

    protected function casts(): array
    {
        return ['senha_hem' => 'hashed'];
    }

    public function funcionarios(): HasMany
    {
        return $this->hasMany(Funcionario::class, 'cnes_hem', 'cnes_hem');
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'cnes_hem', 'cnes_hem');
    }

    public function bolsas(): HasMany
    {
        return $this->hasMany(BolsaSangue::class, 'cnes_hem', 'cnes_hem');
    }
}
