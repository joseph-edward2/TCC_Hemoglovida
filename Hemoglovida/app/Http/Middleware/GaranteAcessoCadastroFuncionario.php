<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class GaranteAcessoCadastroFuncionario
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $instituicao): Response
    {
        if (Gate::allows('cadastrar-funcionario', $instituicao)) {
            return $next($request);
        }

        return redirect()->route($instituicao.'.login');
    }
}
