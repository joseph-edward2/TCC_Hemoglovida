<?php

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

Route::view('/hemocentro/Hem_Agendamento.html', 'hemocentro.Hem_Agendamento')->name('hemocentro.agendamento');
Route::view('/hemocentro/Hem_Alertas.html', 'hemocentro.Hem_Alertas')->name('hemocentro.alertas');
Route::view('/hemocentro/Hem_Atendimento.html', 'hemocentro.Hem_Atendimento')->name('hemocentro.atendimento');
Route::view('/hemocentro/Hem_Estoque.html', 'hemocentro.Hem_Estoque')->name('hemocentro.estoque');
Route::view('/hemocentro/Hem_Login.html', 'hemocentro.Hem_Login')->name('hemocentro.login');
Route::view('/hemocentro/Hem_Pedidos.html', 'hemocentro.Hem_Pedidos')->name('hemocentro.pedidos');
Route::view('/hemocentro/Hem_Tela_Inicial.html', 'hemocentro.Hem_Tela_Inicial')->name('hemocentro.inicio');
Route::view('/hemocentro/Hem_Triagem.html', 'hemocentro.Hem_Triagem')->name('hemocentro.triagem');
Route::view('/hemocentro/Hem_Usuario.html', 'hemocentro.Hem_Usuario')->name('hemocentro.usuario');

Route::view('/hospital/Hemocentro_cadastro_funcionario.html', 'hospital.Hemocentro_cadastro_funcionario')->name('hospital.cadastro-funcionario');
Route::view('/hospital/Hospital_estoque.html', 'hospital.Hospital_estoque')->name('hospital.estoque');
Route::view('/hospital/Hospital_Login.html', 'hospital.Hospital_Login')->name('hospital.login');
Route::view('/hospital/Hospital_pedidos.html', 'hospital.Hospital_pedidos')->name('hospital.pedidos');
?>