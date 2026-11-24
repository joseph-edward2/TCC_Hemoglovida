<?php

use App\Http\Controllers\HemocentroFuncionarioController;
use App\Http\Controllers\HemocentroSessionController;
use App\Http\Controllers\HospitalFuncionarioController;
use App\Http\Controllers\HospitalSessionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'usuario.index')->name('home');

Route::view('/usuario/agendamentos.html', 'usuario.agendamentos')->name('usuario.agendamentos');
Route::view('/usuario/cadastro.html', 'usuario.cadastro')->name('usuario.cadastro');
Route::view('/usuario/caderneta.html', 'usuario.caderneta')->name('usuario.caderneta');
Route::view('/usuario/confirmacao.html', 'usuario.confirmacao')->name('usuario.confirmacao');
Route::view('/usuario/conta.html', 'usuario.conta')->name('usuario.conta');
Route::view('/usuario/dashboard.html', 'usuario.dashboard')->name('usuario.dashboard');
Route::view('/usuario/index.html', 'usuario.index')->name('usuario.index');
Route::view('/usuario/local.html', 'usuario.local')->name('usuario.local');
Route::view('/usuario/login.html', 'usuario.login')->name('usuario.login');
Route::view('/usuario/nova-senha.html', 'usuario.nova-senha')->name('usuario.nova-senha');
Route::view('/usuario/pedidos.html', 'usuario.pedidos')->name('usuario.pedidos');
Route::view('/usuario/recuperar-senha.html', 'usuario.recuperar-senha')->name('usuario.recuperar-senha');

Route::prefix('hemocentro')->name('hemocentro.')->group(function (): void {
    Route::middleware('guest:hemocentro,funcionario')->group(function (): void {
        Route::view('/Hem_Login.blade.php', 'hemocentro.Hem_Login')->name('login');
        Route::post('/Hem_Login.blade.php', [HemocentroSessionController::class, 'store'])
            ->middleware('throttle:10,1,hemocentro-login')
            ->name('login.store');
    });

    Route::middleware('auth:hemocentro,funcionario')->group(function (): void {
        Route::post('/sair', [HemocentroSessionController::class, 'destroy'])->name('logout');

        Route::view('/Hem_Agendamento.blade.php', 'hemocentro.Hem_Agendamento')->name('agendamento');
        Route::view('/Hem_Alertas.blade.php', 'hemocentro.Hem_Alertas')->name('alertas');
        Route::view('/Hem_Atendimento.blade.php', 'hemocentro.Hem_Atendimento')->name('atendimento');
        Route::view('/Hem_Estoque.blade.php', 'hemocentro.Hem_Estoque')->name('estoque');
        Route::view('/Hem_Pedidos.blade.php', 'hemocentro.Hem_Pedidos')->name('pedidos');
        Route::view('/Hem_Tela_Inicial.blade.php', 'hemocentro.Hem_Tela_Inicial')->name('inicio');
        Route::view('/Hem_Triagem.blade.php', 'hemocentro.Hem_Triagem')->name('triagem');
        Route::view('/Hem_Usuario.blade.php', 'hemocentro.Hem_Usuario')->name('usuario');
        Route::view('/Hem_cadastro_funcionario.blade.php', 'hemocentro.Hem_cadastro_funcionario')
            ->middleware('cadastro-funcionario:hemocentro')
            ->name('cadastro-funcionario');

        Route::post('/Hem_cadastro_funcionario.blade.php', [HemocentroFuncionarioController::class, 'store'])
            ->middleware(['throttle:20,1', 'cadastro-funcionario:hemocentro'])
            ->name('funcionario.store');
    });
});

Route::prefix('hospital')->name('hospital.')->group(function (): void {
    Route::middleware('guest:hospital,funcionario')->group(function (): void {
        Route::view('/Hospital_Login.blade.php', 'hospital.Hospital_Login')->name('login');
        Route::post('/Hospital_Login.blade.php', [HospitalSessionController::class, 'store'])
            ->middleware('throttle:10,1,hospital-login')
            ->name('login.store');
    });

    Route::middleware('auth:hospital,funcionario')->group(function (): void {
        Route::post('/sair', [HospitalSessionController::class, 'destroy'])->name('logout');

        Route::view('/Hospital_estoque.blade.php', 'hospital.Hospital_estoque')->name('estoque');
        Route::view('/Hospital_pedidos.blade.php', 'hospital.Hospital_pedidos')->name('pedidos');
        Route::view('/Hospital_usuario.blade.php', 'hospital.Hospital_usuario')->name('usuario');
        Route::view('/Hospital_cadastro_funcionario.blade.php', 'hospital.Hospital_cadastro_funcionario')
            ->middleware('cadastro-funcionario:hospital')
            ->name('cadastro-funcionario');

        Route::post('/Hospital_cadastro_funcionario.blade.php', [HospitalFuncionarioController::class, 'store'])
            ->middleware(['throttle:20,1', 'cadastro-funcionario:hospital'])
            ->name('funcionario.store');
    });
});
