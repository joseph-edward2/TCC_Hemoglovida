<?php

use App\Http\Middleware\GaranteAcessoCadastroFuncionario;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'cadastro-funcionario' => GaranteAcessoCadastroFuncionario::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->is('hospital*')) {
                return route('hospital.login');
            }

            return route('hemocentro.login');
        });

        $middleware->redirectUsersTo(function (Request $request): string {
            if (Auth::guard('hospital')->check()) {
                return route('hospital.estoque');
            }

            return route('hemocentro.inicio');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
