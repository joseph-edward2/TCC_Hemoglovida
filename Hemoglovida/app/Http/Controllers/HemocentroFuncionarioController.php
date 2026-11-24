<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHemocentroFuncionarioRequest;
use App\Models\Funcionario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class HemocentroFuncionarioController extends Controller
{
    public function store(StoreHemocentroFuncionarioRequest $request): RedirectResponse
    {
        $cnesHem = Auth::guard('hemocentro')->user()?->cnes_hem
            ?? Auth::guard('funcionario')->user()?->cnes_hem;

        abort_if($cnesHem === null, 403);

        Funcionario::create(array_merge($request->dadosFuncionario(), [
            'cnes_hem' => $cnesHem,
            'cnpj_hosp' => null,
        ]));

        return redirect()->route('hemocentro.cadastro-funcionario')
            ->with('status', 'Funcionário cadastrado com sucesso.');
    }
}
