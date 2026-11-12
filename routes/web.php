<?php

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

Route::view('/hemocentro/Hem_Agendamento.blade.php', 'hemocentro.Hem_Agendamento')->name('hemocentro.agendamento');
Route::view('/hemocentro/Hem_Alertas.blade.php', 'hemocentro.Hem_Alertas')->name('hemocentro.alertas');
Route::view('/hemocentro/Hem_Atendimento.blade.php', 'hemocentro.Hem_Atendimento')->name('hemocentro.atendimento');
Route::view('/hemocentro/Hem_Estoque.blade.php', 'hemocentro.Hem_Estoque')->name('hemocentro.estoque');
Route::view('/hemocentro/Hem_Login.blade.php', 'hemocentro.Hem_Login')->name('hemocentro.login');
Route::view('/hemocentro/Hem_Pedidos.blade.php', 'hemocentro.Hem_Pedidos')->name('hemocentro.pedidos');
Route::view('/hemocentro/Hem_Tela_Inicial.blade.php', 'hemocentro.Hem_Tela_Inicial')->name('hemocentro.inicio');
Route::view('/hemocentro/Hem_Triagem.blade.php', 'hemocentro.Hem_Triagem')->name('hemocentro.triagem');
Route::view('/hemocentro/Hem_Usuario.blade.php', 'hemocentro.Hem_Usuario')->name('hemocentro.usuario');

Route::view('/hospital/Hemocentro_cadastro_funcionario.blade.php', 'hospital.Hemocentro_cadastro_funcionario')->name('hospital.cadastro-funcionario');
Route::view('/hospital/Hospital_estoque.blade.php', 'hospital.Hospital_estoque')->name('hospital.estoque');
Route::view('/hospital/Hospital_Login.blade.php', 'hospital.Hospital_Login')->name('hospital.login');
Route::view('/hospital/Hospital_pedidos.blade.php', 'hospital.Hospital_pedidos')->name('hospital.pedidos');
?>