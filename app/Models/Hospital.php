<?php

namespace App\Models;

use Database\Factories\HospitalFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Hidden(['senha_hosp'])]
class Hospital extends Authenticatable
{
    /** @use HasFactory<HospitalFactory> */
    use HasFactory;

    protected $table = 'hospital';

    protected $primaryKey = 'cnpj_hosp';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    public function getAuthPasswordName(): string
    {
        return 'senha_hosp';
    }

    protected function casts(): array
    {
        return ['senha_hosp' => 'hashed'];
    }
}
