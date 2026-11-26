<?php

namespace App\Http\Controllers;

use App\Enums\CargoFuncionario;
use App\Http\Requests\StoreHemocentroFuncionarioRequest;
use App\Http\Requests\UpdateHemocentroFuncionarioRequest;
use App\Models\Funcionario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class HemocentroFuncionarioController extends Controller
{
    public function index(): View
    {
        $cnesHem = $this->cnesHemDaSessao();
        $funcionarios = Funcionario::with('hemocentro')->where('cnes_hem', $cnesHem)
            ->orderBy('nome_fun')
            ->get();

        return view('hemocentro.Hem_funcionarios', [
            'funcionarios' => $funcionarios,
            'totalFuncionarios' => $funcionarios->count(),
            'totalResponsaveis' => $funcionarios->where('tipo_fun', CargoFuncionario::Responsavel)->count(),
            'totalAdministrativos' => $funcionarios->where('tipo_fun', CargoFuncionario::Administrativo)->count(),
            'instituicao' => 'hemocentro',
            'cargos' => CargoFuncionario::cases(),
            'codigoFuncionarioLogado' => Auth::guard('funcionario')->user()?->getKey(),
        ]);
    }

    public function store(StoreHemocentroFuncionarioRequest $request): RedirectResponse
    {
        $cnesHem = $this->cnesHemDaSessao();

        Funcionario::create(array_merge($request->dadosFuncionario(), [
            'cnes_hem' => $cnesHem,
            'cnpj_hosp' => null,
        ]));

        return redirect()->route('hemocentro.funcionarios')
            ->with('status', 'Funcionário cadastrado com sucesso.');
    }

    public function update(UpdateHemocentroFuncionarioRequest $request, Funcionario $funcionario): RedirectResponse
    {
        $cnesHem = $this->cnesHemDaSessao();

        abort_if($funcionario->cnes_hem !== $cnesHem, 404);

        $funcionario->update($request->dadosAtualizacao());

        return redirect()->route('hemocentro.funcionarios')
            ->with('status', 'Funcionário atualizado com sucesso.');
    }

    public function destroy(Funcionario $funcionario): RedirectResponse
    {
        $cnesHem = $this->cnesHemDaSessao();

        abort_if($funcionario->cnes_hem !== $cnesHem, 404);

        $funcionarioLogado = Auth::guard('funcionario')->user();
        abort_if(
            $funcionarioLogado !== null && (int) $funcionarioLogado->getKey() === (int) $funcionario->getKey(),
            422,
            'Você não pode excluir seu próprio usuário.'
        );

        $funcionario->delete();

        return redirect()->route('hemocentro.funcionarios')
            ->with('status', 'Funcionário removido com sucesso.');
    }

    private function cnesHemDaSessao(): string
    {
        $cnesHem = Auth::guard('hemocentro')->user()?->cnes_hem
            ?? Auth::guard('funcionario')->user()?->cnes_hem;

        abort_if($cnesHem === null, 403);

        return $cnesHem;
    }
}
