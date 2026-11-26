<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Gate;

class UpdateHospitalFuncionarioRequest extends UpdateFuncionarioRequest
{
    /**
     * Somente o hospital ou seu responsável pode editar funcionários.
     */
    public function authorize(): bool
    {
        return Gate::allows('gerenciar-funcionarios', 'hospital');
    }
}
