<?php

namespace App\Http\Middleware;

use App\Models\Hospital;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GaranteHospitalNaSessao
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(Hospital::daSessao() === null, 403, 'Acesso restrito a usuários do hospital.');

        return $next($request);
    }
}
