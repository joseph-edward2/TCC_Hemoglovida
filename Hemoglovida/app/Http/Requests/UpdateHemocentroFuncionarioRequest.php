<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Gate;

class UpdateHemocentroFuncionarioRequest extends UpdateFuncionarioRequest
{
    /**
     * Somente o hemocentro ou seu responsável pode editar funcionários.
     */
    public function authorize(): bool
    {
        return Gate::allows('gerenciar-funcionarios', 'hemocentro');
    }
}
