<?php

use App\Http\Controllers\HemocentroAtendimentoController;
use App\Http\Controllers\HemocentroDashboardController;
use App\Http\Controllers\HemocentroEstoqueController;
use App\Http\Controllers\HemocentroSessionController;
use App\Http\Controllers\HemocentroTriagemController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'usuario.index')->name('home');

Route::view('/usuario/agendamentos.blade.php', 'usuario.agendamentos')->name('usuario.agendamentos');
Route::view('/usuario/cadastro.blade.php', 'usuario.cadastro')->name('usuario.cadastro');
Route::view('/usuario/caderneta.blade.php', 'usuario.caderneta')->name('usuario.caderneta');
Route::view('/usuario/confirmacao.blade.php', 'usuario.confirmacao')->name('usuario.confirmacao');
Route::view('/usuario/conta.blade.php', 'usuario.conta')->name('usuario.conta');
Route::view('/usuario/dashboard.blade.php', 'usuario.dashboard')->name('usuario.dashboard');
Route::view('/usuario/index.blade.php', 'usuario.index')->name('usuario.index');
Route::view('/usuario/local.blade.php', 'usuario.local')->name('usuario.local');
Route::view('/usuario/login.blade.php', 'usuario.login')->name('usuario.login');
Route::view('/usuario/nova-senha.blade.php', 'usuario.nova-senha')->name('usuario.nova-senha');
Route::view('/usuario/pedidos.blade.php', 'usuario.pedidos')->name('usuario.pedidos');
Route::view('/usuario/recuperar-senha.blade.php', 'usuario.recuperar-senha')->name('usuario.recuperar-senha');

Route::prefix('hemocentro')->name('hemocentro.')->group(function (): void {
    Route::middleware('guest:hemocentro,funcionario')->group(function (): void {
        Route::view('/Hem_Login.blade.php', 'hemocentro.Hem_Login')->name('login');
        Route::post('/Hem_Login.blade.php', [HemocentroSessionController::class, 'store'])
            ->middleware('throttle:10,1,hemocentro-login')->name('login.store');
    });

    Route::middleware('auth:hemocentro,funcionario')->group(function (): void {
        Route::post('/sair', [HemocentroSessionController::class, 'destroy'])->name('logout');
        Route::view('/Hem_Agendamento.blade.php', 'hemocentro.Hem_Agendamento')->name('agendamento');
        Route::view('/Hem_Alertas.blade.php', 'hemocentro.Hem_Alertas')->name('alertas');
        Route::get('/Hem_Atendimento.blade.php', [HemocentroAtendimentoController::class, 'index'])->name('atendimento');
        Route::post('/atendimento/{protocolo}/confirmar-chegada', [HemocentroAtendimentoController::class, 'confirmarChegada'])
            ->whereNumber('protocolo')->name('atendimento.chegada');
        Route::post('/atendimento/{protocolo}/doacao-realizada', [HemocentroAtendimentoController::class, 'concluir'])
            ->whereNumber('protocolo')->name('atendimento.concluir');
        Route::get('/Hem_Estoque.blade.php', HemocentroEstoqueController::class)->name('estoque');
        Route::view('/Hem_Pedidos.blade.php', 'hemocentro.Hem_Pedidos')->name('pedidos');
        Route::get('/Hem_Tela_Inicial.blade.php', HemocentroDashboardController::class)->name('inicio');
        Route::get('/Hem_Triagem.blade.php', [HemocentroTriagemController::class, 'index'])->name('triagem');
        Route::post('/triagem/{isbt}/laudo', [HemocentroTriagemController::class, 'anexar'])->name('triagem.anexar');
        Route::get('/triagem/{isbt}/laudo', [HemocentroTriagemController::class, 'laudo'])->name('triagem.laudo');
        Route::post('/triagem/{isbt}/concluir', [HemocentroTriagemController::class, 'concluir'])->name('triagem.concluir');
        Route::view('/Hem_Usuario.blade.php', 'hemocentro.Hem_Usuario')->name('usuario');
    });
});

Route::view('/hospital/Hemocentro_cadastro_funcionario.blade.php', 'hospital.Hemocentro_cadastro_funcionario')->name('hospital.cadastro-funcionario');
Route::view('/hospital/Hospital_estoque.blade.php', 'hospital.Hospital_estoque')->name('hospital.estoque');
Route::view('/hospital/Hospital_Login.blade.php', 'hospital.Hospital_Login')->name('hospital.login');
Route::view('/hospital/Hospital_pedidos.blade.php', 'hospital.Hospital_pedidos')->name('hospital.pedidos');
