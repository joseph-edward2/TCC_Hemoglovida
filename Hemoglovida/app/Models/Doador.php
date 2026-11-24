<?php

namespace App\Models;

use Database\Factories\DoadorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doador extends Model
{
    /** @use HasFactory<DoadorFactory> */
    use HasFactory;

    protected $table = 'doador';

    protected $primaryKey = 'cpf_doador';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'cpf_doador',
        'nome_doador',
        'email_doador',
        'senha_doador',
        'fone_doador',
        'tipo_sanguineo',
        'endereco_doador',
        'sexo_doador',
        'data_nasc_doador',
    ];

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class, 'cpf_doador', 'cpf_doador');
    }
}
