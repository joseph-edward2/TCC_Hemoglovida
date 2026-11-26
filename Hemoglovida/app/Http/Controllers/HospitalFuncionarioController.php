<?php

namespace App\Http\Controllers;

use App\Enums\CargoFuncionario;
use App\Http\Requests\StoreHospitalFuncionarioRequest;
use App\Http\Requests\UpdateHospitalFuncionarioRequest;
use App\Models\Funcionario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class HospitalFuncionarioController extends Controller
{
    public function index(): View
    {
        $cnpjHosp = $this->cnpjHospDaSessao();
        $funcionarios = Funcionario::with('hospital')->where('cnpj_hosp', $cnpjHosp)
            ->orderBy('nome_fun')
            ->get();

        return view('hospital.Hospital_funcionarios', [
            'funcionarios' => $funcionarios,
            'totalFuncionarios' => $funcionarios->count(),
            'totalResponsaveis' => $funcionarios->where('tipo_fun', CargoFuncionario::Responsavel)->count(),
            'totalAdministrativos' => $funcionarios->where('tipo_fun', CargoFuncionario::Administrativo)->count(),
            'instituicao' => 'hospital',
            'cargos' => CargoFuncionario::cases(),
            'codigoFuncionarioLogado' => Auth::guard('funcionario')->user()?->getKey(),
        ]);
    }

    public function store(StoreHospitalFuncionarioRequest $request): RedirectResponse
    {
        $cnpjHosp = $this->cnpjHospDaSessao();

        Funcionario::create(array_merge($request->dadosFuncionario(), [
            'cnes_hem' => null,
            'cnpj_hosp' => $cnpjHosp,
        ]));

        return redirect()->route('hospital.funcionarios')
            ->with('status', 'Funcionário cadastrado com sucesso.');
    }

    public function update(UpdateHospitalFuncionarioRequest $request, Funcionario $funcionario): RedirectResponse
    {
        $cnpjHosp = $this->cnpjHospDaSessao();

        abort_if($funcionario->cnpj_hosp !== $cnpjHosp, 404);

        $funcionario->update($request->dadosAtualizacao());

        return redirect()->route('hospital.funcionarios')
            ->with('status', 'Funcionário atualizado com sucesso.');
    }

    public function destroy(Funcionario $funcionario): RedirectResponse
    {
        $cnpjHosp = $this->cnpjHospDaSessao();

        abort_if($funcionario->cnpj_hosp !== $cnpjHosp, 404);

        $funcionarioLogado = Auth::guard('funcionario')->user();
        abort_if(
            $funcionarioLogado !== null && (int) $funcionarioLogado->getKey() === (int) $funcionario->getKey(),
            422,
            'Você não pode excluir seu próprio usuário.'
        );

        $funcionario->delete();

        return redirect()->route('hospital.funcionarios')
            ->with('status', 'Funcionário removido com sucesso.');
    }

    private function cnpjHospDaSessao(): string
    {
        $cnpjHosp = Auth::guard('hospital')->user()?->cnpj_hosp
            ?? Auth::guard('funcionario')->user()?->cnpj_hosp;

        abort_if($cnpjHosp === null, 403);

        return $cnpjHosp;
    }
}
