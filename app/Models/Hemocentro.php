<?php

namespace App\Models;

use Database\Factories\HemocentroFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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

    public function getAuthPasswordName(): string
    {
        return 'senha_hem';
    }

    protected function casts(): array
    {
        return ['senha_hem' => 'hashed'];
    }
}
