<?php

namespace App\Providers;

use App\Enums\CargoFuncionario;
use App\Models\Funcionario;
use App\Models\Hospital;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('cadastrar-funcionario', function (mixed $usuario, string $instituicao): bool {
            if (! in_array($instituicao, ['hemocentro', 'hospital'], true)) {
                return false;
            }

            if (Auth::guard($instituicao)->check()) {
                return true;
            }

            $funcionario = Auth::guard('funcionario')->user();

            if (! $funcionario instanceof Funcionario || $funcionario->tipo_fun !== CargoFuncionario::Responsavel) {
                return false;
            }

            $campo = $instituicao === 'hemocentro' ? 'cnes_hem' : 'cnpj_hosp';

            return filled($funcionario->{$campo});
        });

        View::share([
            'hospitalSessao' => fn (): ?Hospital => Hospital::daSessao(),
            'podeCadastrarFuncionario' => fn (string $instituicao): bool => Gate::allows('cadastrar-funcionario', $instituicao),
            'fotoPerfil' => function (): string {
                if (Auth::guard('funcionario')->check()) {
                    return asset('assets/avatar.jpg');
                }

                if (Auth::guard('hospital')->check() || Auth::guard('hemocentro')->check()) {
                    return asset('assets/avatar_instituicao.jpg');
                }

                return asset('assets/avatar.jpg');
            },
            'nomeExibido' => function (): string {
                if (Auth::guard('funcionario')->check()) {
                    return Auth::guard('funcionario')->user()->nome_fun ?? '';
                }

                if (Auth::guard('hospital')->check()) {
                    return Auth::guard('hospital')->user()->nome_hosp ?? '';
                }

                if (Auth::guard('hemocentro')->check()) {
                    return 'Hemocentro';
                }

                return 'Usuário';
            },
        ]);
    }
}
