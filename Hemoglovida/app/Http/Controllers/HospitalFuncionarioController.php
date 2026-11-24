<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHospitalFuncionarioRequest;
use App\Models\Funcionario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class HospitalFuncionarioController extends Controller
{
    public function store(StoreHospitalFuncionarioRequest $request): RedirectResponse
    {
        $cnpjHosp = Auth::guard('hospital')->user()?->cnpj_hosp
            ?? Auth::guard('funcionario')->user()?->cnpj_hosp;

        abort_if($cnpjHosp === null, 403);

        Funcionario::create(array_merge($request->dadosFuncionario(), [
            'cnes_hem' => null,
            'cnpj_hosp' => $cnpjHosp,
        ]));

        return redirect()->route('hospital.cadastro-funcionario')
            ->with('status', 'Funcionário cadastrado com sucesso.');
    }
}
