<?php

namespace App\Models;

use Database\Factories\FuncionarioFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Hidden(['cpf_fun', 'senha_fun'])]
class Funcionario extends Authenticatable
{
    /** @use HasFactory<FuncionarioFactory> */
    use HasFactory;

    protected $table = 'funcionario';

    protected $primaryKey = 'cod_reg_fun';

    public $timestamps = false;

    public function getAuthPasswordName(): string
    {
        return 'senha_fun';
    }

    protected function casts(): array
    {
        return ['senha_fun' => 'hashed'];
    }
}
