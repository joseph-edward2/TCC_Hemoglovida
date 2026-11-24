<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Gate;

class StoreHospitalFuncionarioRequest extends StoreFuncionarioRequest
{
    /**
     * Somente o hospital ou seu responsável pode cadastrar funcionários.
     */
    public function authorize(): bool
    {
        return Gate::allows('cadastrar-funcionario', 'hospital');
    }
}
