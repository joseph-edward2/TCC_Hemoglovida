<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Gate;

class StoreHemocentroFuncionarioRequest extends StoreFuncionarioRequest
{
    /**
     * Somente o hemocentro ou seu responsável pode cadastrar funcionários.
     */
    public function authorize(): bool
    {
        return Gate::allows('cadastrar-funcionario', 'hemocentro');
    }
}
