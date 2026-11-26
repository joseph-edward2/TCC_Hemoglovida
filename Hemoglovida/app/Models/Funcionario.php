<?php

namespace App\Models;

use App\Enums\CargoFuncionario;
use Database\Factories\FuncionarioFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use InvalidArgumentException;

#[Hidden(['cpf_fun', 'senha_fun'])]
class Funcionario extends Authenticatable
{
    /** @use HasFactory<FuncionarioFactory> */
    use HasFactory;

    protected $table = 'funcionario';

    protected $primaryKey = 'cod_reg_fun';

    public $timestamps = false;

    protected $fillable = [
        'cnes_hem',
        'cnpj_hosp',
        'nome_fun',
        'senha_fun',
        'tipo_fun',
        'cpf_fun',
    ];

    public function getAuthPasswordName(): string
    {
        return 'senha_fun';
    }

    protected function casts(): array
    {
        return [
            'senha_fun' => 'hashed',
            'tipo_fun' => CargoFuncionario::class,
        ];
    }

    protected function cpfMascarado(): Attribute
    {
        return Attribute::get(function (): string {
            $cpf = preg_replace('/\D/', '', (string) $this->cpf_fun) ?? '';

            if (strlen($cpf) !== 11) {
                return '—';
            }

            return '***.'.substr($cpf, 3, 3).'.'.substr($cpf, 6, 3).'-**';
        });
    }

    protected static function booted(): void
    {
        parent::booted();

        static::saving(function (Funcionario $funcionario): void {
            $temHemocentro = filled($funcionario->cnes_hem);
            $temHospital = filled($funcionario->cnpj_hosp);

            if ($temHemocentro === $temHospital) {
                throw new InvalidArgumentException(
                    'O funcionário deve estar vinculado a exatamente uma instituição: hospital ou hemocentro.'
                );
            }
        });
    }

    public function hemocentro(): BelongsTo
    {
        return $this->belongsTo(Hemocentro::class, 'cnes_hem', 'cnes_hem');
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class, 'cnpj_hosp', 'cnpj_hosp');
    }

    public function instituicao(): Hospital|Hemocentro|null
    {
        return filled($this->cnpj_hosp) ? $this->hospital : $this->hemocentro;
    }
}
