<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class GaranteGerenciarFuncionarios
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $instituicao): Response
    {
        Gate::authorize('gerenciar-funcionarios', $instituicao);

        return $next($request);
    }
}
